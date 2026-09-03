import type { AttendanceStatus } from '@/types/dashboard';

export type AttendanceResource = {
    id: number;
    user_id: number;
    attendance_date: string;
    check_in_time: string | null;
    check_out_time: string | null;
    attendance_status: AttendanceStatus;
    branch_area: 'inside_branch_area' | 'outside_branch_area' | null;
    notes: string | null;
    latitude: string | null;
    longitude: string | null;
    created_at: string;
    updated_at: string;
};

export type AttendanceRecapResource = {
    id: number;
    user: {
        id: number;
        nip: string;
        name: string;
    } | null;
    office: {
        id: number;
        office_code: string;
        office_name: string;
        city: string;
    } | null;
    attendance_date: string;
    check_in_time: string | null;
    check_out_time: string | null;
    attendance_status: AttendanceStatus;
    late_minutes: number | null;
    latitude: string | null;
    longitude: string | null;
};

export type AttendanceSummaryStatus =
    | 'H'
    | 'A'
    | 'I'
    | 'C'
    | 'S'
    | 'D';

export type AttendanceSummaryUser = {
    nip: string;
    name: string;
    position: string;
    dates: Record<string, AttendanceSummaryStatus>;
};

export type AttendanceSummaryCounts = {
    total_users: number;
    hadir: number;
    absen: number;
    izin: number;
    cuti: number;
    sakit: number;
    dinas: number;
};

export type AttendanceSummaryFilters = {
    start_date: string;
    end_date: string;
};

export type AttendanceRecapFilters = {
    start_date: string;
    end_date: string;
    search: string;
    office_id: string;
    attendance_status: 'present' | 'late' | '';
};