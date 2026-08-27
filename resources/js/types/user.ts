export type UserStatus = 'pending' | 'active' | 'rejected';

export type RoleResource = {
    id: number;
    name: string;
};

export type OfficeResource = {
    id: number;
    office_code: string;
    office_name: string;
};

export type UserResource = {
    id: number;
    role_id: number;
    office_id: number;
    nip: string | null;
    name: string;
    position: string;
    email: string;
    phone: string;
    join_date: string;
    city: string;
    status: UserStatus;
    approved_by: number | null;
    approved_at: string | null;
    rejected_reason: string | null;
    role?: RoleResource;
    office?: OfficeResource;
    approvedBy?: {
        id: number;
        name: string;
    } | null;
    created_at: string;
    updated_at: string;
};

export type UserFilters = {
    search: string;
    sort: string;
    direction: 'asc' | 'desc';
    status: UserStatus;
};

export type UserCounts = {
    active: number;
    pending: number;
    rejected: number;
};

export type UserSortColumn = 'nip' | 'name' | 'join_date';

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    first_page_url: string | null;
    from: number | null;
    last_page: number;
    last_page_url: string | null;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};
