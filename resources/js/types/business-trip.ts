export type BusinessTripStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';

export type BusinessTripResource = {
    id: number;
    user_id: number;
    start_date: string;
    end_date: string;
    destination: string;
    purpose: string;
    notes: string | null;
    attachment: string | null;
    status: BusinessTripStatus;
    approval_notes: string | null;
    user?: { id: number; name: string; nip: string };
    approver?: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
};

export type BusinessTripFilters = {
    search: string;
    status: BusinessTripStatus | '';
};

export type BusinessTripCan = {
    manage: boolean;
};
