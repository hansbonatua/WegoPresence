export type AttendanceComplaintStatus = 'pending' | 'approved' | 'rejected';

export type AttendanceComplaintResource = {
    id: number;
    attendance_id: number;
    user_id: number;
    complaint_reason: string;
    status: AttendanceComplaintStatus;
    approval_notes: string | null;
    user?: {
        id: number;
        name: string;
        nip: string;
        office?: {
            id: number;
            office_code: string;
            office_name: string;
            city: string;
        } | null;
    };
    attendance?: {
        id: number;
        user_id: number;
        attendance_date: string;
        check_in_time: string | null;
        check_out_time: string | null;
        attendance_status: string | null;
    } | null;
    approver?: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    updated_at: string;
};

export type AttendanceComplaintFilters = {
    search: string;
    status: AttendanceComplaintStatus | '';
};

export type AttendanceComplaintCan = {
    manage: boolean;
};

export type AttendanceOption = {
    id: number;
    attendance_date: string;
    check_in_time: string | null;
    check_out_time: string | null;
    attendance_status: string | null;
};