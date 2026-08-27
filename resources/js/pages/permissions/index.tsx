import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Eye, Plus, Search, SearchX, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { approve, cancel, create, index, reject } from '@/routes/permissions';
import type {
    Paginated,
    PermissionCan,
    PermissionFilters,
    PermissionResource,
    PermissionStatus,
    PermissionType,
} from '@/types';

type IndexProps = {
    permissions: Paginated<PermissionResource>;
    filters: PermissionFilters;
    can: PermissionCan;
};

const statusStyles: Record<PermissionStatus, string> = {
    pending:
        'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    approved:
        'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    rejected:
        'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    cancelled:
        'border-transparent bg-slate-100 text-slate-600 dark:bg-slate-800/40 dark:text-slate-400',
};

const typeLabels: Record<PermissionType, string> = {
    personal: 'Personal',
    official: 'Official',
};

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function StatusBadge({ status }: { status: PermissionStatus }) {
    return (
        <Badge variant="outline" className={statusStyles[status]}>
            {capitalize(status)}
        </Badge>
    );
}

export default function PermissionsIndex({ permissions, filters, can }: IndexProps) {
    const currentUserId = usePage<{ auth: { user: { id: number } } }>().props
        .auth.user.id;

    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState<PermissionStatus | ''>(filters.status);
    const [isLoading, setIsLoading] = useState(false);
    const [selected, setSelected] = useState<PermissionResource | null>(null);
    const [toCancel, setToCancel] = useState<PermissionResource | null>(null);
    const [toReview, setToReview] = useState<{
        permission: PermissionResource;
        action: 'approve' | 'reject';
    } | null>(null);
    const [approvalNotes, setApprovalNotes] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search === filters.search && status === filters.status) {
                return;
            }

            router.get(
                index(),
                {
                    search: search || undefined,
                    status: status || undefined,
                    page: 1,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    only: ['permissions', 'filters'],
                },
            );
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, status, filters.search, filters.status]);

    useEffect(() => {
        const stopStart = router.on('start', () => setIsLoading(true));
        const stopFinish = router.on('finish', () => setIsLoading(false));

        return () => {
            stopStart();
            stopFinish();
        };
    }, []);

    function openReview(
        permission: PermissionResource,
        action: 'approve' | 'reject',
    ) {
        setApprovalNotes('');
        setToReview({ permission, action });
    }

    function confirmReview() {
        if (!toReview) {
            return;
        }

        setIsSubmitting(true);

        const route =
            toReview.action === 'approve'
                ? approve({ permission: toReview.permission.id })
                : reject({ permission: toReview.permission.id });

        router.post(
            route,
            { approval_notes: approvalNotes || undefined },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsSubmitting(false);
                    setToReview(null);
                },
            },
        );
    }

    function confirmCancel() {
        if (!toCancel) {
            return;
        }

        setIsSubmitting(true);

        router.post(cancel({ permission: toCancel.id }), undefined, {
            preserveScroll: true,
            onFinish: () => {
                setIsSubmitting(false);
                setToCancel(null);
            },
        });
    }

    return (
        <>
            <Head title="Permissions" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between bg-sky-400 text-white p-4 rounded-lg">
                    <Heading
                        title="Permissions"
                        description={
                            can.manage
                                ? 'Review and process permission requests'
                                : 'Submit and track your permission requests'
                        }
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            Add Permission
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Permission requests</CardTitle>
                            <CardDescription>
                                {permissions.total > 0
                                    ? `${permissions.total} request${permissions.total === 1 ? '' : 's'} found`
                                    : 'No permission requests found'}
                            </CardDescription>
                        </div>

                        <div className="flex w-full flex-col gap-2 sm:max-w-md sm:flex-row">
                            {can.manage && (
                                <Select
                                    value={status}
                                    onValueChange={(value) =>
                                        setStatus(
                                            value as PermissionStatus | '',
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        className="w-full sm:w-40"
                                        aria-label="Filter by status"
                                    >
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">
                                            All statuses
                                        </SelectItem>
                                        {(
                                            [
                                                'pending',
                                                'approved',
                                                'rejected',
                                                'cancelled',
                                            ] as PermissionStatus[]
                                        ).map((item) => (
                                            <SelectItem
                                                key={item}
                                                value={item}
                                            >
                                                {capitalize(item)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}

                            <div className="relative w-full">
                                <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Search reason or employee..."
                                    className="pl-9"
                                    aria-label="Search permissions"
                                />
                            </div>
                        </div>
                    </CardHeader>

                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                        {can.manage && (
                                            <th className="px-4 py-3">
                                                Employee
                                            </th>
                                        )}
                                        <th className="px-4 py-3">Type</th>
                                        <th className="px-4 py-3">Dates</th>
                                        <th className="px-4 py-3">Reason</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3">
                                            Submitted
                                        </th>
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
                                                        colSpan={7}
                                                        className="px-4 py-4"
                                                    >
                                                        <Skeleton className="h-8 w-full" />
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    ) : permissions.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={7}>
                                                <div className="flex flex-col items-center gap-2 px-4 py-16 text-center">
                                                    <div className="rounded-full bg-muted p-3">
                                                        <SearchX className="size-6 text-muted-foreground" />
                                                    </div>
                                                    <p className="font-medium">
                                                        {filters.search ||
                                                        filters.status
                                                            ? 'No requests match your filters'
                                                            : 'No permission requests yet'}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {filters.search ||
                                                        filters.status
                                                            ? 'Try adjusting your search or status filter.'
                                                            : can.manage
                                                              ? 'Permission requests will appear here once employees submit them.'
                                                              : 'Submit your first permission request to get started.'}
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        permissions.data.map((permission) => {
                                            const canCancel =
                                                permission.user_id ===
                                                    currentUserId &&
                                                permission.status === 'pending';
                                            const canReview =
                                                can.manage &&
                                                permission.status === 'pending';

                                            return (
                                                <tr
                                                    key={permission.id}
                                                    className="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                                                >
                                                    {can.manage && (
                                                        <td className="px-4 py-3 font-medium">
                                                            {permission.user
                                                                ?.name ?? '—'}
                                                        </td>
                                                    )}
                                                    <td className="px-4 py-3">
                                                        {typeLabels[
                                                            permission.type
                                                        ] ?? permission.type}
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {permission.start_date}
                                                        {permission.end_date &&
                                                        permission.end_date !==
                                                            permission.start_date
                                                            ? ` → ${permission.end_date}`
                                                            : ''}
                                                    </td>
                                                    <td className="max-w-64 px-4 py-3 text-muted-foreground">
                                                        <span className="line-clamp-2">
                                                            {permission.reason}
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge
                                                            status={
                                                                permission.status
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {permission.created_at?.slice(
                                                            0,
                                                            10,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                aria-label="View details"
                                                                onClick={() =>
                                                                    setSelected(
                                                                        permission,
                                                                    )
                                                                }
                                                            >
                                                                <Eye />
                                                            </Button>

                                                            {canCancel && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label="Cancel request"
                                                                    onClick={() =>
                                                                        setToCancel(
                                                                            permission,
                                                                        )
                                                                    }
                                                                >
                                                                    <X className="text-destructive" />
                                                                </Button>
                                                            )}

                                                            {canReview && (
                                                                <>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        aria-label="Approve request"
                                                                        onClick={() =>
                                                                            openReview(
                                                                                permission,
                                                                                'approve',
                                                                            )
                                                                        }
                                                                    >
                                                                        <Check className="text-green-600" />
                                                                    </Button>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        aria-label="Reject request"
                                                                        onClick={() =>
                                                                            openReview(
                                                                                permission,
                                                                                'reject',
                                                                            )
                                                                        }
                                                                    >
                                                                        <X className="text-destructive" />
                                                                    </Button>
                                                                </>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        })
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {permissions.data.length > 0 && (
                            <div className="border-t p-4">
                                <Pagination links={permissions.links} />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={selected !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelected(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Permission request</DialogTitle>
                        <DialogDescription>
                            Submitted by {selected?.user?.name ?? 'employee'} ·{' '}
                            {selected?.created_at?.slice(0, 10)}
                        </DialogDescription>
                    </DialogHeader>

                    {selected && (
                        <div className="grid gap-4 text-sm">
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Status
                                </span>
                                <StatusBadge status={selected.status} />
                            </div>

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Type
                                </span>
                                <span className="font-medium">
                                    {typeLabels[selected.type] ??
                                        selected.type}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Date range
                                </span>
                                <span className="font-medium">
                                    {selected.start_date}
                                    {selected.end_date &&
                                    selected.end_date !== selected.start_date
                                        ? ` → ${selected.end_date}`
                                        : ''}
                                </span>
                            </div>

                            <div>
                                <p className="mb-1 text-muted-foreground">
                                    Reason
                                </p>
                                <p className="whitespace-pre-wrap rounded-md bg-muted p-3">
                                    {selected.reason}
                                </p>
                            </div>

                            {selected.approval_notes && (
                                <div>
                                    <p className="mb-1 text-muted-foreground">
                                        Approval notes
                                    </p>
                                    <p className="whitespace-pre-wrap rounded-md bg-muted p-3">
                                        {selected.approval_notes}
                                    </p>
                                </div>
                            )}

                            {selected.approver && (
                                <div className="flex items-center justify-between gap-4">
                                    <span className="text-muted-foreground">
                                        Reviewed by
                                    </span>
                                    <span className="font-medium">
                                        {selected.approver.name}
                                    </span>
                                </div>
                            )}
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            variant="secondary"
                            onClick={() => setSelected(null)}
                        >
                            Close
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={toReview !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setToReview(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {toReview?.action === 'approve'
                                ? 'Approve request?'
                                : 'Reject request?'}
                        </DialogTitle>
                        <DialogDescription>
                            {toReview && (
                                <>
                                    {toReview.permission.user?.name} ·{' '}
                                    {typeLabels[toReview.permission.type]} ·{' '}
                                    {toReview.permission.start_date}
                                    {toReview.permission.end_date !==
                                    toReview.permission.start_date
                                        ? ` → ${toReview.permission.end_date}`
                                        : ''}
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="approval_notes">
                            Notes{' '}
                            <span className="text-muted-foreground">
                                (optional)
                            </span>
                        </Label>
                        <textarea
                            id="approval_notes"
                            value={approvalNotes}
                            onChange={(event) =>
                                setApprovalNotes(event.target.value)
                            }
                            rows={3}
                            placeholder="Add a note for the employee..."
                            className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                        />
                    </div>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setToReview(null)}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>

                        <Button
                            variant={
                                toReview?.action === 'approve'
                                    ? 'default'
                                    : 'destructive'
                            }
                            onClick={confirmReview}
                            disabled={isSubmitting}
                        >
                            {isSubmitting && <Spinner />}
                            {toReview?.action === 'approve'
                                ? 'Approve'
                                : 'Reject'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={toCancel !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setToCancel(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel request?</DialogTitle>
                        <DialogDescription>
                            {toCancel && (
                                <>
                                    This will cancel the{' '}
                                    {typeLabels[toCancel.type]} permission
                                    request for{' '}
                                    {toCancel.start_date}
                                    {toCancel.end_date !== toCancel.start_date
                                        ? ` → ${toCancel.end_date}`
                                        : ''}
                                    . This cannot be undone.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setToCancel(null)}
                            disabled={isSubmitting}
                        >
                            Keep request
                        </Button>

                        <Button
                            variant="destructive"
                            onClick={confirmCancel}
                            disabled={isSubmitting}
                        >
                            {isSubmitting && <Spinner />}
                            Cancel request
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

PermissionsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Permissions',
            href: index(),
        },
    ],
};
