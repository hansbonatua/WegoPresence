export type PermissionStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type PermissionType = 'personal' | 'official';

export type PermissionResource = {
    id: number;
    user_id: number;
    type: PermissionType;
    start_date: string;
    end_date: string;
    reason: string;
    status: PermissionStatus;
    approval_notes: string | null;
    user?: {
        id: number;
        name: string;
    };
    approver?: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    updated_at: string;
};

export type PermissionFilters = {
    search: string;
    status: PermissionStatus | '';
};

export type PermissionCan = {
    manage: boolean;
};
