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
import { approve, cancel, create, index, reject } from '@/routes/attendance-complaints';
import type {
    AttendanceComplaintCan,
    AttendanceComplaintFilters,
    AttendanceComplaintResource,
    AttendanceComplaintStatus,
    Paginated,
} from '@/types';

type IndexProps = {
    complaints: Paginated<AttendanceComplaintResource>;
    filters: AttendanceComplaintFilters;
    can: AttendanceComplaintCan;
};

const statusStyles: Record<AttendanceComplaintStatus, string> = {
    pending:
        'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    approved:
        'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    rejected:
        'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function StatusBadge({ status }: { status: AttendanceComplaintStatus }) {
    return (
        <Badge variant="outline" className={statusStyles[status]}>
            {capitalize(status)}
        </Badge>
    );
}

export default function AttendanceComplaintsIndex({
    complaints,
    filters,
    can,
}: IndexProps) {
    const currentUserId = usePage<{ auth: { user: { id: number } } }>().props
        .auth.user.id;

    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState<AttendanceComplaintStatus | ''>(
        filters.status,
    );
    const [isLoading, setIsLoading] = useState(false);
    const [selected, setSelected] =
        useState<AttendanceComplaintResource | null>(null);
    const [toCancel, setToCancel] =
        useState<AttendanceComplaintResource | null>(null);
    const [toReview, setToReview] = useState<{
        complaint: AttendanceComplaintResource;
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
                    only: ['complaints', 'filters'],
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
        complaint: AttendanceComplaintResource,
        action: 'approve' | 'reject',
    ) {
        setApprovalNotes('');
        setToReview({ complaint, action });
    }

    function confirmReview() {
        if (!toReview) {
            return;
        }

        setIsSubmitting(true);

        const route =
            toReview.action === 'approve'
                ? approve({ complaint: toReview.complaint.id })
                : reject({ complaint: toReview.complaint.id });

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

        router.post(cancel({ complaint: toCancel.id }), undefined, {
            preserveScroll: true,
            onFinish: () => {
                setIsSubmitting(false);
                setToCancel(null);
            },
        });
    }

    return (
        <>
            <Head title="Attendance Complaints" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-5 rounded-lg bg-sky-400 p-4 text-white sm:flex-row sm:items-center sm:justify-between">
                    <Heading
                        title="Attendance Complaints"
                        description={
                            can.manage
                                ? 'Review and process attendance complaints'
                                : 'Submit and track your attendance complaints'
                        }
                    />

                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            Add Complaint
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader className="gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <CardTitle>Attendance complaints</CardTitle>
                            <CardDescription>
                                {complaints.total > 0
                                    ? `${complaints.total} complaint${complaints.total === 1 ? '' : 's'} found`
                                    : 'No attendance complaints found'}
                            </CardDescription>
                        </div>

                        <div className="flex w-full flex-col gap-2 sm:max-w-md sm:flex-row">
                            {can.manage && (
                                <Select
                                    value={status}
                                    onValueChange={(value) =>
                                        setStatus(
                                            value as AttendanceComplaintStatus | '',
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
                                            ] as AttendanceComplaintStatus[]
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
                                    placeholder="Search reason, name, or NIP..."
                                    className="pl-9"
                                    aria-label="Search attendance complaints"
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
                                        <th className="px-4 py-3">
                                            Attendance date
                                        </th>
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
                                                        colSpan={6}
                                                        className="px-4 py-4"
                                                    >
                                                        <Skeleton className="h-8 w-full" />
                                                    </td>
                                                </tr>
                                            ),
                                        )
                                    ) : complaints.data.length === 0 ? (
                                        <tr>
                                            <td colSpan={6}>
                                                <div className="flex flex-col items-center gap-2 px-4 py-16 text-center">
                                                    <div className="rounded-full bg-muted p-3">
                                                        <SearchX className="size-6 text-muted-foreground" />
                                                    </div>
                                                    <p className="font-medium">
                                                        {filters.search ||
                                                        filters.status
                                                            ? 'No complaints match your filters'
                                                            : 'No attendance complaints yet'}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {filters.search ||
                                                        filters.status
                                                            ? 'Try adjusting your search or status filter.'
                                                            : can.manage
                                                              ? 'Complaints will appear here once employees submit them.'
                                                              : 'Submit your first attendance complaint to get started.'}
                                                    </p>
                                                </div>
                                            </td>
                                        </tr>
                                    ) : (
                                        complaints.data.map((complaint) => {
                                            const canCancel =
                                                complaint.user_id ===
                                                    currentUserId &&
                                                complaint.status === 'pending';
                                            const canReview =
                                                can.manage &&
                                                complaint.status === 'pending';

                                            return (
                                                <tr
                                                    key={complaint.id}
                                                    className="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                                                >
                                                    {can.manage && (
                                                        <td className="px-4 py-3">
                                                            <span className="font-medium">
                                                                {complaint
                                                                    .user
                                                                    ?.name ??
                                                                    '—'}
                                                            </span>
                                                            {complaint.user
                                                                ?.nip && (
                                                                <span className="block text-xs text-muted-foreground">
                                                                    NIP{' '}
                                                                    {
                                                                        complaint
                                                                            .user
                                                                            .nip
                                                                    }
                                                                </span>
                                                            )}
                                                        </td>
                                                    )}
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {complaint.attendance
                                                            ?.attendance_date ??
                                                            '—'}
                                                    </td>
                                                    <td className="max-w-64 px-4 py-3 text-muted-foreground">
                                                        <span className="line-clamp-2">
                                                            {
                                                                complaint.complaint_reason
                                                            }
                                                        </span>
                                                    </td>
                                                    <td className="px-4 py-3">
                                                        <StatusBadge
                                                            status={
                                                                complaint.status
                                                            }
                                                        />
                                                    </td>
                                                    <td className="px-4 py-3 text-muted-foreground">
                                                        {complaint.created_at?.slice(
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
                                                                        complaint,
                                                                    )
                                                                }
                                                            >
                                                                <Eye />
                                                            </Button>

                                                            {canCancel && (
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label="Cancel complaint"
                                                                    onClick={() =>
                                                                        setToCancel(
                                                                            complaint,
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
                                                                        aria-label="Approve complaint"
                                                                        onClick={() =>
                                                                            openReview(
                                                                                complaint,
                                                                                'approve',
                                                                            )
                                                                        }
                                                                    >
                                                                        <Check className="text-green-600" />
                                                                    </Button>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        aria-label="Reject complaint"
                                                                        onClick={() =>
                                                                            openReview(
                                                                                complaint,
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

                        {complaints.data.length > 0 && (
                            <div className="border-t p-4">
                                <Pagination links={complaints.links} />
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
                        <DialogTitle>Attendance complaint</DialogTitle>
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
                                    Attendance date
                                </span>
                                <span className="font-medium">
                                    {selected.attendance?.attendance_date ?? '—'}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Check-in / Check-out
                                </span>
                                <span className="font-medium">
                                    {selected.attendance?.check_in_time ?? '—'}
                                    {' / '}
                                    {selected.attendance?.check_out_time ?? '—'}
                                </span>
                            </div>

                            <div className="flex items-center justify-between gap-4">
                                <span className="text-muted-foreground">
                                    Attendance status
                                </span>
                                <span className="font-medium">
                                    {selected.attendance
                                        ?.attendance_status ?? '—'}
                                </span>
                            </div>

                            <div>
                                <p className="mb-1 text-muted-foreground">
                                    Complaint reason
                                </p>
                                <p className="whitespace-pre-wrap rounded-md bg-muted p-3">
                                    {selected.complaint_reason}
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
                                ? 'Approve complaint?'
                                : 'Reject complaint?'}
                        </DialogTitle>
                        <DialogDescription>
                            {toReview && (
                                <>
                                    {toReview.complaint.user?.name} ·{' '}
                                    {toReview.complaint.attendance
                                        ?.attendance_date ?? '—'}
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
                        <DialogTitle>Cancel complaint?</DialogTitle>
                        <DialogDescription>
                            {toCancel && (
                                <>
                                    This will remove the complaint for{' '}
                                    {toCancel.attendance?.attendance_date ?? '—'}
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
                            Keep complaint
                        </Button>

                        <Button
                            variant="destructive"
                            onClick={confirmCancel}
                            disabled={isSubmitting}
                        >
                            {isSubmitting && <Spinner />}
                            Cancel complaint
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AttendanceComplaintsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Attendance Complaints',
            href: index(),
        },
    ],
};