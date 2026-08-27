export type SickLeaveStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type SickLeaveResource = {
    id: number;
    user_id: number;
    start_date: string;
    end_date: string;
    reason: string;
    status: SickLeaveStatus;
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

export type SickLeaveFilters = {
    search: string;
    status: SickLeaveStatus | '';
};

export type SickLeaveCan = {
    manage: boolean;
};
