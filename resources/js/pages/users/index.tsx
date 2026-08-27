import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    Check,
    Eye,
    Pencil,
    Plus,
    Search,
    SearchX,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Pagination } from '@/components/ui/pagination';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    activate,
    create,
    destroy,
    edit,
    index,
    reject,
    show,
} from '@/routes/users';
import type {
    Paginated,
    UserCounts,
    UserFilters,
    UserResource,
    UserSortColumn,
    UserStatus,
} from '@/types';

type IndexProps = {
    users: Paginated<UserResource>;
    filters: UserFilters;
    counts: UserCounts;
    can: {
        review: boolean;
    };
};

const tabs: { status: UserStatus; label: string }[] = [
    { status: 'active', label: 'Active Accounts' },
    { status: 'pending', label: 'Pending Registrations' },
    { status: 'rejected', label: 'Rejected' },
];

const statusStyles: Record<UserStatus, string> = {
    active: 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    pending:
        'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    rejected:
        'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function StatusBadge({ status }: { status: UserStatus }) {
    return (
        <Badge variant="outline" className={statusStyles[status]}>
            {capitalize(status)}
        </Badge>
    );
}

function SortableHeader({
    column,
    sort,
    direction,
    onSort,
    children,
}: {
    column: UserSortColumn;
    sort: string;
    direction: 'asc' | 'desc';
    onSort: (column: UserSortColumn) => void;
    children: ReactNode;
}) {
    const isActive = sort === column;
    const Icon = !isActive
        ? ArrowUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <th className="px-4 py-3">
            <button
                type="button"
                onClick={() => onSort(column)}
                className="inline-flex items-center gap-1 transition-colors hover:text-foreground"
            >
                {children}
                <Icon className="size-3.5" />
            </button>
        </th>
    );
}

export default function UsersIndex({ users, filters, counts, can }: IndexProps) {
    const [search, setSearch] = useState(filters.search);
    const [isLoading, setIsLoading] = useState(false);
    const [userToDelete, setUserToDelete] = useState<UserResource | null>(null);
    const [isDeleting, setIsDeleting] = useState(false);
    const [userToActivate, setUserToActivate] = useState<UserResource | null>(
        null,
    );
    const [isActivating, setIsActivating] = useState(false);
    const [userToReject, setUserToReject] = useState<UserResource | null>(null);
    const [rejectReason, setRejectReason] = useState('');
    const [isRejecting, setIsRejecting] = useState(false);

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search === filters.search) {
                return;
            }

            router.get(
                index(),
                {
                    search: search || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['users', 'filters', 'counts'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, filters.search]);

    useEffect(() => {
        const stopStart = router.on('start', () => setIsLoading(true));
        const stopFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    function handleTab(status: UserStatus) {
        router.get(
            index(),
            { status, search: search || undefined, page: 1 },
            { preserveState: false, preserveScroll: true },
        );
    }

    function handleSort(column: UserSortColumn) {
        const direction =
            filters.sort === column && filters.direction === 'asc'
                ? 'desc'
                : 'asc';

        router.get(
            index(),
            {
                sort: column,
                direction,
                search: search || undefined,
                page: 1,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function confirmDelete() {
        if (!userToDelete) {
            return;
        }

        setIsDeleting(true);

        router.delete(destroy({ user: userToDelete.id }).url, {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
                setUserToDelete(null);
            },
        });
    }

    function confirmActivate() {
        if (!userToActivate) {
            return;
        }

        setIsActivating(true);

        router.post(activate({ user: userToActivate.id }).url, undefined, {
            preserveScroll: true,
            onFinish: () => {
                setIsActivating(false);
                setUserToActivate(null);
            },
        });
    }

    function confirmReject() {
        if (!userToReject) {
            return;
        }

        setIsRejecting(true);

        router.post(
            reject({ user: userToReject.id }).url,
            { rejected_reason: rejectReason },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsRejecting(false);
                    setUserToReject(null);
                    setRejectReason('');
                },
            },
        );
    }

    const description =
        filters.status === 'pending'
            ? 'Registrations waiting for an administrator to activate or reject them.'
            : filters.status === 'rejected'
              ? 'Registrations that were declined, with the reason given.'
              : 'Manage employee accounts, roles, and offices';

    const emptyMessage =
        filters.status === 'pending'
            ? filters.search
                ? 'No pending registrations match your search.'
                : 'No pending registrations. New registrations will appear here.'
            : filters.status === 'rejected'
              ? filters.search
                  ? 'No rejected registrations match your search.'
                  : 'No rejected registrations yet.'
              : filters.search
                ? 'No users match your search'
                : 'No users yet';

    const isPendingTab = filters.status === 'pending';

    return (
        <>
            <Head title="Users" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between bg-sky-400 text-white p-4 rounded-lg">
                    <Heading
                        title="Users"
                        description={description}
                    />

                    {filters.status === 'active' && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                Add user
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader className="gap-4">
                        <div className="flex flex-wrap items-center gap-2">
                            {tabs.map((tab) => (
                                <Button
                                    key={tab.status}
                                    variant={
                                        filters.status === tab.status
                                            ? 'default'
                                            : 'ghost'
                                    }
                                    onClick={() => handleTab(tab.status)}
                                >
                                    {tab.label}
                                    <span className="ml-1 text-xs opacity-70">
                                        {counts[tab.status]}
                                    </span>
                                </Button>
                            ))}
                        </div>

                        <div className="flex items-center justify-between gap-4">
                            <CardTitle className="capitalize">
                                {filters.status} accounts
                            </CardTitle>

                            <div className="relative w-full sm:max-w-xs">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search NIP, name, or email..."
                                    className="pl-9"
                                    aria-label="Search users"
                                />
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        <SortableHeader
                                            column="nip"
                                            sort={filters.sort}
                                            direction={filters.direction}
                                            onSort={handleSort}
                                        >
                                            NIP
                                        </SortableHeader>
                                        <SortableHeader
                                            column="name"
                                            sort={filters.sort}
                                            direction={filters.direction}
                                            onSort={handleSort}
                                        >
                                            Name
                                        </SortableHeader>
                                        <th className="px-4 py-3">Position</th>
                                        <th className="px-4 py-3">Role</th>
                                        <th className="px-4 py-3">Office</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">Email</th>
                                        <th className="px-4 py-3">Phone</th>
                                        <SortableHeader
                                            column="join_date"
                                            sort={filters.sort}
                                            direction={filters.direction}
                                            onSort={handleSort}
                                        >
                                            Join date
                                        </SortableHeader>
                                        {filters.status === 'rejected' && (
                                            <th className="px-4 py-3">
                                                Rejection reason
                                            </th>
                                        )}
                                        <th className="px-4 py-3 text-right">
                                            Action
                                        </th>
                                    </tr>
                                </thead>

                                <tbody>
                                    {isLoading ? (
                                        Array.from({ length: 5 }).map(
                                            (_, index) => (
                                                <tr
                                                    key={index}
                                                    className="border-b"
                                                >
                                                    <td
                                                        colSpan={12}
                                                        className="px-4 py-4"
                                                    >
                                                        <Skeleton className="h-8 w-full" />
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    ) : users.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={12}>
                                                <div className="flex flex-col items-center gap-2 px-4 py-16 text-center">
                                                    <div className="rounded-full bg-muted p-3">
                                                        <SearchX className="size-6 text-muted-foreground" />
                                                    </div>
                                                    <p className="font-medium">
                                                        {emptyMessage}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        Try adjusting your
                                                        search keywords.
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        users.data.map((user) => (
                                            <tr
                                                key={user.id}
                                                className="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    {user.nip ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 font-medium">
                                                    {user.name}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {user.position}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {user.role
                                                        ? capitalize(
                                                              user.role.name,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {user.office
                                                        ? user.office
                                                              .office_name
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <StatusBadge
                                                        status={user.status}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {user.email}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {user.phone}
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {user.join_date}
                                                </td>
                                                {filters.status ===
                                                    'rejected' && (
                                                    <td className="max-w-64 px-4 py-3 text-muted-foreground">
                                                        <p className="truncate">
                                                            {user.rejected_reason ??
                                                                '—'}
                                                        </p>
                                                        {user.approvedBy && (
                                                            <p className="text-xs text-muted-foreground/70">
                                                                Reviewed by{' '}
                                                                {
                                                                    user
                                                                        .approvedBy
                                                                        .name
                                                                }
                                                            </p>
                                                        )}
                                                    </td>
                                                )}
                                                <td className="px-4 py-3">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {isPendingTab &&
                                                        can.review ? (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    asChild
                                                                    aria-label={`View ${user.name}`}
                                                                >
                                                                    <Link
                                                                        href={show(
                                                                            {
                                                                                user: user.id,
                                                                            },
                                                                        )}
                                                                    >
                                                                        <Eye />
                                                                    </Link>
                                                                </Button>

                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Activate ${user.name}`}
                                                                    onClick={() =>
                                                                        setUserToActivate(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <Check className="text-green-600" />
                                                                </Button>

                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Reject ${user.name}`}
                                                                    onClick={() =>
                                                                        setUserToReject(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <X className="text-destructive" />
                                                                </Button>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    asChild
                                                                    aria-label={`Edit ${user.name}`}
                                                                >
                                                                    <Link
                                                                        href={edit(
                                                                            {
                                                                                user: user.id,
                                                                            },
                                                                        )}
                                                                    >
                                                                        <Pencil />
                                                                    </Link>
                                                                </Button>

                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Delete ${user.name}`}
                                                                    onClick={() =>
                                                                        setUserToDelete(
                                                                            user,
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="text-destructive" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {users.data.length > 0 && (
                            <div className="border-t p-4">
                                <Pagination links={users.links} />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={userToDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUserToDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete user?</DialogTitle>
                        <DialogDescription>
                            {userToDelete && (
                                <>
                                    This will soft delete{' '}
                                    <span className="font-medium">
                                        {userToDelete.name}
                                    </span>{' '}
                                    (NIP {userToDelete.nip}). They will no
                                    longer be able to sign in.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setUserToDelete(null)}
                            disabled={isDeleting}
                        >
                            Cancel
                        </Button>

                        <Button
                            variant="destructive"
                            onClick={confirmDelete}
                            disabled={isDeleting}
                        >
                            {isDeleting && <Spinner />}
                            Delete user
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={userToActivate !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUserToActivate(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Activate registration?</DialogTitle>
                        <DialogDescription>
                            {userToActivate && (
                                <>
                                    Activating{' '}
                                    <span className="font-medium">
                                        {userToActivate.name}
                                    </span>{' '}
                                    {userToActivate.nip ? (
                                        <>
                                            will preserve their NIP (
                                            <span className="font-mono">
                                                {userToActivate.nip}
                                            </span>
                                            ) and allow them to sign in.
                                        </>
                                    ) : (
                                        <>
                                            will assign them a NIP (
                                            <span className="font-mono">
                                                010XXX
                                            </span>
                                            ) and allow them to sign in.
                                        </>
                                    )}
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setUserToActivate(null)}
                            disabled={isActivating}
                        >
                            Cancel
                        </Button>

                        <Button
                            onClick={confirmActivate}
                            disabled={isActivating}
                        >
                            {isActivating && <Spinner />}
                            Activate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={userToReject !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setUserToReject(null);
                        setRejectReason('');
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject registration?</DialogTitle>
                        <DialogDescription>
                            {userToReject && (
                                <>
                                    Rejecting{' '}
                                    <span className="font-medium">
                                        {userToReject.name}
                                    </span>{' '}
                                    will prevent them from signing in. The
                                    reason will be shown to administrators.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="rejected-reason">
                            Rejection reason
                        </Label>
                        <Textarea
                            id="rejected-reason"
                            value={rejectReason}
                            onChange={(event) =>
                                setRejectReason(event.target.value)
                            }
                            placeholder="e.g. Missing documents, incorrect office"
                            rows={3}
                        />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => {
                                setUserToReject(null);
                                setRejectReason('');
                            }}
                            disabled={isRejecting}
                        >
                            Cancel
                        </Button>

                        <Button
                            variant="destructive"
                            onClick={confirmReject}
                            disabled={isRejecting || rejectReason.trim() === ''}
                        >
                            {isRejecting && <Spinner />}
                            Reject
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

UsersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
    ],
};