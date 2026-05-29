<?php

namespace App\Services;

use App\Constants\AuditActionType;
use App\Constants\AuditMenuCode;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Repositories\AttendanceRepository;
use App\Repositories\MemberRepository;
use App\Repositories\WorshipRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    protected AttendanceRepository $attendanceRepository;
    protected WorshipRepository $worshipRepository;
    protected MemberRepository $memberRepository;

    public function __construct(
        AttendanceRepository $attendanceRepository,
        WorshipRepository $worshipRepository,
        MemberRepository $memberRepository
    ) {
        $this->attendanceRepository = $attendanceRepository;
        $this->worshipRepository    = $worshipRepository;
        $this->memberRepository     = $memberRepository;
    }

    /**
     * 출석 일괄 UPSERT
     * input: service_id, attend_date, records[ {member_id, status, note} ]
     */
    public function recordBulk(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $serviceId  = (int) $input['service_id'];
            $attendDate = $input['attend_date'];
            $records    = $input['records'] ?? [];

            $worship = $this->worshipRepository->getWorshipById($serviceId);
            if ($worship === null || (int) $worship->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '예배 정보를 찾을 수 없습니다.', 404);
            }

            if (empty($records)) {
                return ApiResponse::success([
                    'service_id'   => $serviceId,
                    'attend_date'  => $attendDate,
                    'saved_count'  => 0,
                ]);
            }

            $memberIds = array_unique(array_map(fn ($r) => (int) $r['member_id'], $records));
            $validIds  = DB::table('reg_members')
                ->where('church_id', $churchId)
                ->where('is_deleted', 0)
                ->whereIn('id', $memberIds)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $validSet = array_flip($validIds);

            $now  = now();
            $rows = [];
            foreach ($records as $r) {
                $mid = (int) $r['member_id'];
                if (!isset($validSet[$mid])) {
                    continue;
                }
                $rows[] = [
                    'member_id'   => $mid,
                    'service_id'  => $serviceId,
                    'church_id'   => $churchId,
                    'attend_date' => $attendDate,
                    'status'      => $r['status'] ?? 'unknown',
                    'note'        => $r['note'] ?? null,
                    'recorded_by' => $authMemberId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            $saved = 0;
            if (!empty($rows)) {
                $this->attendanceRepository->upsertAttendances($rows);
                $saved = count($rows);
            }

            AuditLogHelper::logAction(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::ATTENDANCE,
                actionType:  AuditActionType::BULK_UPDATE,
                summary:     "출석 일괄 기록: {$worship->name} {$attendDate} (저장 {$saved}건, 스킵 " . (count($records) - $saved) . "건)",
                targetId:    (string) $serviceId,
                targetLabel: $worship->name,
                detail:      ['attend_date' => $attendDate, 'saved_count' => $saved, 'skipped_count' => count($records) - $saved],
            );

            return ApiResponse::success([
                'service_id'   => $serviceId,
                'attend_date'  => $attendDate,
                'saved_count'  => $saved,
                'skipped_count' => count($records) - $saved,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AttendanceService] recordBulk error: " . $e->getMessage(), "attendance");
            return ApiResponse::fail('INTERNAL_ERROR', '출석 기록 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 특정 예배·날짜의 출석부 (교회 내 활성 교인 전체, 미체크 포함)
     */
    public function getListByService(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $serviceId      = (int) $input['service_id'];
            $attendDate     = $input['attend_date'];
            $organizationId = isset($input['organization_id']) ? (int) $input['organization_id'] : null;

            $worship = $this->worshipRepository->getWorshipById($serviceId);
            if ($worship === null || (int) $worship->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '예배 정보를 찾을 수 없습니다.', 404);
            }

            $roster = $this->attendanceRepository->getRosterForService($churchId, $serviceId, $attendDate, $organizationId);

            return ApiResponse::success([
                'service_id'  => $serviceId,
                'attend_date' => $attendDate,
                'list'        => $roster,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AttendanceService] getListByService error: " . $e->getMessage(), "attendance");
            return ApiResponse::fail('INTERNAL_ERROR', '출석부 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /**
     * 특정 교인의 기간별 출석 이력
     */
    public function getListByMember(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $memberId  = (int) $input['member_id'];
            $fromDate  = $input['from_date'] ?? null;
            $toDate    = $input['to_date'] ?? null;
            $serviceId = isset($input['service_id']) ? (int) $input['service_id'] : null;

            $member = $this->memberRepository->getMemberById($memberId);
            if ($member === null || (int) $member->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '교인 정보를 찾을 수 없습니다.', 404);
            }

            $list = $this->attendanceRepository->getHistoryByMember($memberId, $fromDate, $toDate, $serviceId);

            return ApiResponse::success([
                'member_id' => $memberId,
                'list'      => $list,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[AttendanceService] getListByMember error: " . $e->getMessage(), "attendance");
            return ApiResponse::fail('INTERNAL_ERROR', '출석 이력 조회 중 오류가 발생했습니다.', 500);
        }
    }
}
