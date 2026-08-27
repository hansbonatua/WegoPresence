import { Head, router } from '@inertiajs/react';
import { BadgeCheck, Check, X } from 'lucide-react';
import { useState } from 'react';
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
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { activate, index, reject, show } from '@/routes/users';
import type { UserResource, UserStatus } from '@/types';

type ShowUserProps = {
    user: UserResource;
    can: {
        activate: boolean;
        reject: boolean;
        update: boolean;
    };
};

const statusStyles: Record<UserStatus, string> = {
    active: 'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    pending:
        'border-transparent bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    rejected:
        'border-transparent bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
};

function Field({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs font-medium text-muted-foreground uppercase tracking-wide">
                {label}
            </dt>
            <dd className="text-sm">{value}</dd>
        </div>
    );
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function ShowUser({ user, can }: ShowUserProps) {
    const [isActivating, setIsActivating] = useState(false);
    const [confirmActivate, setConfirmActivate] = useState(false);
    const [isRejecting, setIsRejecting] = useState(false);
    const [confirmReject, setConfirmReject] = useState(false);
    const [rejectReason, setRejectReason] = useState('');

    function handleActivate() {
        setIsActivating(true);

        router.post(activate({ user: user.id }).url, undefined, {
            preserveScroll: true,
            onFinish: () => {
                setIsActivating(false);
                setConfirmActivate(false);
            },
        });
    }

    function handleReject() {
        setIsRejecting(true);

        router.post(
            reject({ user: user.id }).url,
            { rejected_reason: rejectReason },
            {
                preserveScroll: true,
                onFinish: () => {
                    setIsRejecting(false);
                    setConfirmReject(false);
                    setRejectReason('');
                },
            },
        );
    }

    return (
        <>
            <Head title={user.name} />

            <div className="flex flex-col gap-6">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between bg-sky-400 text-white p-4 rounded-lg">
                    <Heading
                        title={user.name}
                        description={`Account details for ${user.name}`}
                    />

                    {can.activate && (
                        <Button
                            onClick={() => setConfirmActivate(true)}
                            disabled={isActivating}
                        >
                            <BadgeCheck />
                            Activate Account
                        </Button>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Personal information</CardTitle>
                            <CardDescription>
                                Details submitted during registration
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <dl className="grid gap-6 sm:grid-cols-2">
                                <Field label="NIP" value={user.nip ?? 'Not assigned'} />
                                <Field label="Status" value={<StatusBadge status={user.status} />} />
                                <Field label="Name" value={user.name} />
                                <Field label="Position" value={user.position} />
                                <Field label="Email" value={user.email} />
                                <Field label="Phone" value={user.phone} />
                                <Field label="Join date" value={user.join_date} />
                                <Field label="City" value={user.city} />
                                <Field label="Office" value={user.office ? `${user.office.office_name} (${user.office.office_code})` : '—'} />
                                <Field label="Role" value={user.role ? user.role.name : '—'} />
                                <Field label="Registered" value={formatDateTime(user.created_at)} />
                                <Field label="Approved by" value={user.approvedBy?.name ?? '—'} />
                                {user.approved_at && (
                                    <Field label="Approved at" value={formatDateTime(user.approved_at)} />
                                )}
                                {user.rejected_reason && (
                                    <div className="sm:col-span-2">
                                        <Field label="Rejection reason" value={user.rejected_reason} />
                                    </div>
                                )}
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Actions</CardTitle>
                            <CardDescription>
                                {user.status === 'pending'
                                    ? 'Review this registration'
                                    : 'Manage this account'}
                            </CardDescription>
                        </CardHeader>

                        <CardContent className="flex flex-col gap-3">
                            {user.status === 'pending' && can.reject && (
                                <Button
                                    variant="destructive"
                                    onClick={() => setConfirmReject(true)}
                                    disabled={isRejecting}
                                >
                                    <X />
                                    Reject
                                </Button>
                            )}

                            {user.status !== 'pending' && (
                                <p className="text-sm text-muted-foreground">
                                    This account has already been reviewed.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Dialog open={confirmActivate} onOpenChange={setConfirmActivate}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Activate registration?</DialogTitle>
                        <DialogDescription>
                            Activating{' '}
                            <span className="font-medium">{user.name}</span>{' '}
                            {user.nip ? (
                                <>
                                    will preserve their NIP (
                                    <span className="font-mono">
                                        {user.nip}
                                    </span>
                                    ) and allow them to sign in.
                                </>
                            ) : (
                                <>
                                    will assign them a NIP (
                                    <span className="font-mono">010XXX</span>)
                                    and allow them to sign in.
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setConfirmActivate(false)}
                            disabled={isActivating}
                        >
                            Cancel
                        </Button>

                        <Button onClick={handleActivate} disabled={isActivating}>
                            {isActivating && <Spinner />}
                            <Check />
                            Activate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmReject} onOpenChange={setConfirmReject}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject registration?</DialogTitle>
                        <DialogDescription>
                            Rejecting{' '}
                            <span className="font-medium">{user.name}</span>{' '}
                            will prevent them from signing in. The reason will be
                            shown to administrators.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-2">
                        <Label htmlFor="rejected-reason">Rejection reason</Label>
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
                                setConfirmReject(false);
                                setRejectReason('');
                            }}
                            disabled={isRejecting}
                        >
                            Cancel
                        </Button>

                        <Button
                            variant="destructive"
                            onClick={handleReject}
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

function StatusBadge({ status }: { status: UserStatus }) {
    return (
        <Badge variant="outline" className={statusStyles[status]}>
            {status.charAt(0).toUpperCase() + status.slice(1)}
        </Badge>
    );
}

ShowUser.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index(),
        },
        {
            title: 'User Details',
            href: show({ user: 0 }),
        },
    ],
};
