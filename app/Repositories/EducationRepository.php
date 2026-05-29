<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

class EducationRepository
{
    // ─────────────── 교육 과정 (reg_education_courses) ───────────────

    public function getCourseList(int $churchId, array $filters): array
    {
        $query = DB::table('reg_education_courses')
            ->where('church_id', $churchId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from_date'])) {
            $query->where('start_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '<=', $filters['to_date']);
            });
        }
        if (!empty($filters['keyword'])) {
            $kw = '%' . $filters['keyword'] . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'LIKE', $kw)
                  ->orWhere('description', 'LIKE', $kw);
            });
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('start_date', 'desc')
            ->orderBy('id', 'desc')
            ->forPage($page, $size)
            ->get()
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    public function getCourseById(int $courseId): ?stdClass
    {
        return DB::table('reg_education_courses')
            ->where('id', $courseId)
            ->first();
    }

    public function insertCourse(array $data): int
    {
        return (int) DB::table('reg_education_courses')->insertGetId($data);
    }

    public function updateCourse(int $courseId, array $data): int
    {
        return DB::table('reg_education_courses')
            ->where('id', $courseId)
            ->update($data);
    }

    public function deleteCourse(int $courseId): int
    {
        return DB::table('reg_education_courses')
            ->where('id', $courseId)
            ->delete();
    }

    public function countEnrollmentsByCourse(int $courseId): int
    {
        return DB::table('reg_education_records')
            ->where('course_id', $courseId)
            ->count();
    }

    // ─────────────── 수강 기록 (reg_education_records) ───────────────

    public function getEnrollment(int $courseId, int $memberId): ?stdClass
    {
        return DB::table('reg_education_records')
            ->where('course_id', $courseId)
            ->where('member_id', $memberId)
            ->first();
    }

    public function insertEnrollment(array $data): int
    {
        return (int) DB::table('reg_education_records')->insertGetId($data);
    }

    public function updateEnrollment(int $enrollmentId, array $data): int
    {
        return DB::table('reg_education_records')
            ->where('id', $enrollmentId)
            ->update($data);
    }

    public function getEnrollmentsByCourse(int $courseId, array $filters): array
    {
        $query = DB::table('reg_education_records as r')
            ->join('reg_members as m', 'm.id', '=', 'r.member_id')
            ->where('r.course_id', $courseId)
            ->where('m.is_deleted', 0);

        if (!empty($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        $total = (clone $query)->count();

        $page = max(1, (int) ($filters['page'] ?? 1));
        $size = max(1, min(100, (int) ($filters['size'] ?? 20)));

        $list = $query->orderBy('r.status', 'asc')
            ->orderBy('m.name', 'asc')
            ->forPage($page, $size)
            ->get([
                'r.id as enrollment_id', 'r.progress', 'r.status',
                'r.created_at', 'r.updated_at',
                'm.id as member_id', 'm.member_no', 'm.name', 'm.email', 'm.phone',
            ])
            ->toArray();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'list'  => $list,
        ];
    }

    public function getCoursesByMember(int $memberId): array
    {
        return DB::table('reg_education_records as r')
            ->join('reg_education_courses as c', 'c.id', '=', 'r.course_id')
            ->where('r.member_id', $memberId)
            ->orderBy('c.start_date', 'desc')
            ->get([
                'r.id as enrollment_id', 'r.progress', 'r.status as enrollment_status',
                'c.id as course_id', 'c.name as course_name', 'c.description',
                'c.start_date', 'c.end_date', 'c.status as course_status',
            ])
            ->toArray();
    }
}
