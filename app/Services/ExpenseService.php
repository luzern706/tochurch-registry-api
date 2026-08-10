<?php

namespace App\Services;

use App\Constants\AuditMenuCode;
use App\Constants\FileUploadConstants;
use App\Helpers\ApiResponse;
use App\Helpers\AuditLogHelper;
use App\Helpers\JwtHelper;
use App\Helpers\LogHelper;
use App\Helpers\S3FileHelper;
use App\Repositories\ExpenseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class ExpenseService
{
    private const FILLABLE = [
        'expense_date', 'category', 'amount', 'purpose', 'vendor',
        'method', 'ledger', 'note', 'receipt_files',
    ];

    protected ExpenseRepository $expenseRepository;

    public function __construct(ExpenseRepository $expenseRepository)
    {
        $this->expenseRepository = $expenseRepository;
    }

    // TODO(권한): 지출도 헌금과 동일하게 민감 정보. 추후 재무팀/담당자 권한 도입 시 강화.

    public function getExpenseList(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = $this->expenseRepository->getExpenseList($churchId, $filters);
            return ApiResponse::success($data);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] getExpenseList error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '지출 목록 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function getExpenseDetail(int $authMemberId, int $expenseId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $expense = $this->expenseRepository->getExpenseById($expenseId);
            if ($expense === null || (int) $expense->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '지출 기록을 찾을 수 없습니다.', 404);
            }

            return ApiResponse::success(['expense' => $expense]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] getExpenseDetail error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '지출 상세 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function registerExpense(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            if (array_key_exists('receipt_files', $data)) {
                $data['receipt_files'] = $data['receipt_files'] ? json_encode($data['receipt_files'], JSON_UNESCAPED_UNICODE) : null;
            }
            $data['church_id']   = $churchId;
            $data['recorded_by'] = $authMemberId;

            $newId = $this->expenseRepository->insertExpense($data);

            AuditLogHelper::logCreate(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EXPENSE,
                summary:     "지출 등록: {$data['category']} {$data['amount']}원 ({$data['purpose']})",
                targetId:    (string) $newId,
                targetLabel: $data['category'],
                created:     ['category' => $data['category'], 'amount' => $data['amount'], 'purpose' => $data['purpose']],
            );

            return ApiResponse::success(['expense_id' => $newId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] registerExpense error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '지출 등록 중 오류가 발생했습니다.', 500);
        }
    }

    public function updateExpense(int $authMemberId, int $expenseId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $expense = $this->expenseRepository->getExpenseById($expenseId);
            if ($expense === null || (int) $expense->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '지출 기록을 찾을 수 없습니다.', 404);
            }

            $data = array_intersect_key($input, array_flip(self::FILLABLE));
            if (array_key_exists('receipt_files', $data)) {
                $data['receipt_files'] = $data['receipt_files'] ? json_encode($data['receipt_files'], JSON_UNESCAPED_UNICODE) : null;
            }
            if (!empty($data)) {
                $this->expenseRepository->updateExpense($expenseId, $data);
            }

            $beforeArr = (array) $expense;
            $beforeArr['receipt_files'] = json_encode($beforeArr['receipt_files'] ?? [], JSON_UNESCAPED_UNICODE);
            $afterArr  = array_merge($beforeArr, $data);
            AuditLogHelper::logUpdate(
                memberId:      $authMemberId,
                churchId:      $churchId,
                menuCode:      AuditMenuCode::EXPENSE,
                summary:       "지출 수정: expense#{$expenseId} ({$expense->category})",
                targetId:      (string) $expenseId,
                targetLabel:   $expense->category,
                before:        $beforeArr,
                after:         $afterArr,
                compareFields: array_keys($data),
            );

            return ApiResponse::success(['expense_id' => $expenseId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] updateExpense error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '지출 수정 중 오류가 발생했습니다.', 500);
        }
    }

    public function deleteExpense(int $authMemberId, int $expenseId): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $expense = $this->expenseRepository->getExpenseById($expenseId);
            if ($expense === null || (int) $expense->church_id !== $churchId) {
                return ApiResponse::fail('NOT_FOUND', '지출 기록을 찾을 수 없습니다.', 404);
            }

            $this->expenseRepository->deleteExpense($expenseId);

            AuditLogHelper::logDelete(
                memberId:    $authMemberId,
                churchId:    $churchId,
                menuCode:    AuditMenuCode::EXPENSE,
                summary:     "지출 삭제(hard): expense#{$expenseId} ({$expense->category} {$expense->amount}원)",
                targetId:    (string) $expenseId,
                targetLabel: $expense->category,
                deleted:     ['category' => $expense->category, 'amount' => (int) $expense->amount, 'expense_date' => $expense->expense_date],
            );

            return ApiResponse::success(['expense_id' => $expenseId]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] deleteExpense error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '지출 삭제 중 오류가 발생했습니다.', 500);
        }
    }

    /** 항목별 조회 탭 — 선택 항목 총지출 / 전체 지출 대비 비율 / 항목별 차트 데이터 */
    public function getCategoryStats(int $authMemberId, array $input): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $fromDate = $input['from_date'];
            $toDate   = $input['to_date'];
            $category = $input['category'] ?? null;

            $categoryTotals = $this->expenseRepository->getCategoryTotals($churchId, $fromDate, $toDate);
            $overallTotal   = array_sum(array_column($categoryTotals, 'total_amount'));

            $selected = null;
            if ($category !== null) {
                foreach ($categoryTotals as $row) {
                    if ($row['category'] === $category) {
                        $selected = $row;
                        break;
                    }
                }
            }
            $selectedTotal = $selected['total_amount'] ?? 0;
            $percent       = $overallTotal > 0 ? round($selectedTotal / $overallTotal * 100, 1) : null;

            return ApiResponse::success([
                'from_date'        => $fromDate,
                'to_date'          => $toDate,
                'category'         => $category,
                'overall_total'    => $overallTotal,
                'selected_total'   => $selectedTotal,
                'percent_of_total' => $percent,
                'category_totals'  => $categoryTotals,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] getCategoryStats error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '지출 항목별 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    /** 증빙 관리 탭 — 전체 건수 / 증빙 미첨부 건수 / 완료율 */
    public function getReceiptStats(int $authMemberId, array $filters): JsonResponse
    {
        try {
            $churchId = JwtHelper::getChurchIdFromRequest();
            if ($churchId === null) {
                return ApiResponse::fail('TOKEN_INVALID', '인증이 필요합니다.', 401);
            }

            $summary = $this->expenseRepository->getReceiptSummary($churchId, $filters);
            $rate = $summary['total'] > 0
                ? round(($summary['total'] - $summary['missing']) / $summary['total'] * 100, 1)
                : null;

            return ApiResponse::success([
                'total'            => $summary['total'],
                'missing'          => $summary['missing'],
                'completion_rate'  => $rate,
            ]);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] getReceiptStats error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '증빙 통계 조회 중 오류가 발생했습니다.', 500);
        }
    }

    public function uploadReceipt(UploadedFile $file, int $churchId): JsonResponse
    {
        $err = S3FileHelper::validate($file, FileUploadConstants::ALLOWED_DOCUMENT_EXTENSIONS, '증빙 파일');
        if ($err !== null) {
            return ApiResponse::fail('INVALID_FILE_TYPE', $err, 400);
        }

        try {
            $upload = S3FileHelper::upload($file, 'uploads/expense_receipts', "church_{$churchId}_receipt_" . time() . '_' . uniqid());
            S3FileHelper::cleanupLocalTemp($upload['local_path']);
            return ApiResponse::success([
                'url'  => $upload['s3_url'],
                'name' => $file->getClientOriginalName(),
            ]);
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('UPLOAD_FAILED', '증빙 파일 업로드에 실패했습니다.', 500);
        } catch (\Exception $e) {
            LogHelper::logWrite("[ExpenseService] uploadReceipt error: " . $e->getMessage(), "expense");
            return ApiResponse::fail('INTERNAL_ERROR', '증빙 파일 업로드 중 오류가 발생했습니다.', 500);
        }
    }
}
