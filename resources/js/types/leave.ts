export type LeaveStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type LeaveResource = {
    id: number;
    user_id: number;
    start_date: string;
    end_date: string;
    reason: string;
    status: LeaveStatus;
    approval_notes: string | null;
    user?: {
        id: number;
        name: string;
        nip: string;
    };
    approver?: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    updated_at: string;
};

export type LeaveFilters = {
    search: string;
    status: LeaveStatus | '';
};

export type LeaveCan = {
    manage: boolean;
};
