import { Head, router } from '@inertiajs/react';
import { LogIn, LogOut, SearchX } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import AttendanceCameraDialog from '@/components/attendance-camera-dialog';
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
import { Spinner } from '@/components/ui/spinner';
import { checkIn, checkOut, index } from '@/routes/attendance';
import type { AttendanceResource } from '@/types';

type Props = {
    today: AttendanceResource | null;
    history: AttendanceResource[];
    flash?: {
        success?: string;
        error?: string;
    };
};

const statusStyles: Record<string, string> = {
    present:
        'border-transparent bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    late: 'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300',
    absent:
        'border-transparent bg-muted text-muted-foreground',
};

const POSITION_MAX_AGE_MS = 30_000;

const START_ATTENDANCE_TIME = '08:46 WIB';

const statusLabels: Record<string, string> = {
    present: 'Hadir',
    late: 'Terlambat',
    absent: 'Absen',
};

function statusLabel(status: string | null): string {
    if (!status) {
        return '—';
    }

    return statusLabels[status] ?? status;
}

export default function AttendanceIndex({ today, history, flash }: Props) {
    const [isLocating, setIsLocating] = useState(false);
    const [isCheckingIn, setIsCheckingIn] = useState(false);
    const [isCheckingOut, setIsCheckingOut] = useState(false);
    const [cameraMode, setCameraMode] = useState<
        'check-in' | 'check-out' | null
    >(null);

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    function handleGeolocationError(error: GeolocationPositionError) {
        const messages: Record<number, string> = {
            [error.PERMISSION_DENIED]:
                'Location permission denied. Please allow location access and try again.',
            [error.POSITION_UNAVAILABLE]:
                'Your location could not be determined. Make sure GPS is enabled and try again.',
            [error.TIMEOUT]:
                'Location request timed out. Please try again.',
        };

        toast.error(
            messages[error.code] ??
                'Unable to get your location. Please try again.',
        );
    }

    function submitCheckIn(photo: File) {
        if (!navigator.geolocation) {
            toast.error(
                'Your browser does not support location access. Please use a supported browser.',
            );

            return;
        }

        setIsLocating(true);

        navigator.geolocation.getCurrentPosition(
            (position) => {
                const positionAgeMs = Date.now() - position.timestamp;

                if (positionAgeMs > POSITION_MAX_AGE_MS) {
                    setIsLocating(false);
                    toast.error(
                        'Your location data is stale. Please refresh your location and try again.',
                    );

                    return;
                }

                if (positionAgeMs < -POSITION_MAX_AGE_MS) {
                    setIsLocating(false);
                    toast.error(
                        'Your location data is stale. Please refresh your location and try again.',
                    );

                    return;
                }

                setIsLocating(false);
                setIsCheckingIn(true);

                const formData = new FormData();
                formData.append('photo', photo);
                formData.append('latitude', String(position.coords.latitude));
                formData.append('longitude', String(position.coords.longitude));
                formData.append('position_timestamp', String(position.timestamp));

                router.post(checkIn(), formData, {
                    preserveScroll: true,
                    onFinish: () => setIsCheckingIn(false),
                });
            },
            (error) => {
                setIsLocating(false);
                handleGeolocationError(error);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            },
        );
    }

    function submitCheckOut(photo: File) {
        setIsCheckingOut(true);

        const formData = new FormData();
        formData.append('photo', photo);

        router.post(checkOut(), formData, {
            preserveScroll: true,
            onFinish: () => setIsCheckingOut(false),
        });
    }

    function handlePhotoCaptured(photo: File) {
        const mode = cameraMode;

        setCameraMode(null);

        if (mode === null) {
            return;
        }

        if (mode === 'check-out') {
            submitCheckOut(photo);

            return;
        }

        submitCheckIn(photo);
    }

    const canCheckIn = today === null;
    const canCheckOut = today !== null && today.check_out_time === null;
    const isBusy = isLocating || isCheckingIn || isCheckingOut;

    return (
        <>
            <Head title="Attendance" />

            <div className="flex flex-col gap-6">
                <div className="bg-sky-400 p-4 text-white rounded-lg">
                    <Heading
                        title="Attendance"
                        description="Check in and check out for today"
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardDescription>Today's Status</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {today ? (
                                <Badge
                                    variant="outline"
                                    className={statusStyles[today.attendance_status ?? '']}
                                >
                                    {statusLabel(today.attendance_status)}
                                </Badge>
                            ) : (
                                <p className="text-2xl font-semibold tracking-tight text-muted-foreground">
                                    Not checked in yet
                                </p>
                            )}
                            <p className="mt-3 text-xs text-muted-foreground">
                                Jam masuk: {START_ATTENDANCE_TIME}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>Check In</CardDescription>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between gap-4">
                            <p className="text-3xl font-semibold tracking-tight">
                                {today?.check_in_time ?? '—'}
                            </p>
                            <Button
                                onClick={() => setCameraMode('check-in')}
                                disabled={!canCheckIn || isBusy}
                            >
                                {isLocating || isCheckingIn ? (
                                    <Spinner />
                                ) : (
                                    <LogIn />
                                )}
                                {isLocating
                                    ? 'Locating...'
                                    : isCheckingIn
                                      ? 'Checking in...'
                                      : 'Check in'}
                            </Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>Check Out</CardDescription>
                        </CardHeader>
                        <CardContent className="flex items-center justify-between gap-4">
                            <p className="text-3xl font-semibold tracking-tight">
                                {today?.check_out_time ?? '—'}
                            </p>
                            <Button
                                variant="secondary"
                                onClick={() => setCameraMode('check-out')}
                                disabled={!canCheckOut || isBusy}
                            >
                                {isCheckingOut && <Spinner />}
                                <LogOut />
                                Check out
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Attendance History</CardTitle>
                        <CardDescription>Your last 5 records</CardDescription>
                    </CardHeader>
                    <CardContent className="p-0">
                        {history.length === 0 ? (
                            <div className="flex flex-col items-center gap-2 px-4 py-16 text-center">
                                <div className="rounded-full bg-muted p-3">
                                    <SearchX className="size-6 text-muted-foreground" />
                                </div>
                                <p className="font-medium">No records yet</p>
                                <p className="text-sm text-muted-foreground">
                                    Your attendance history will appear here
                                    after your first check-in.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                            <th className="px-4 py-3">
                                                Date
                                            </th>
                                            <th className="px-4 py-3">
                                                Check In
                                            </th>
                                            <th className="px-4 py-3">
                                                Check Out
                                            </th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {history.map((item) => (
                                            <tr
                                                key={item.id}
                                                className="border-b transition-colors last:border-b-0 hover:bg-muted/50"
                                            >
                                                <td className="px-4 py-3">
                                                    {item.attendance_date}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {item.check_in_time ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {item.check_out_time ?? '—'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant="outline"
                                                        className={
                                                            statusStyles[
                                                                item
                                                                    .attendance_status ?? ''
                                                            ]
                                                        }
                                                    >
                                                        {statusLabel(
                                                            item
                                                                .attendance_status,
                                                        )}
                                                    </Badge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <AttendanceCameraDialog
                    open={cameraMode !== null}
                    title={
                        cameraMode === 'check-out'
                            ? 'Check Out Photo'
                            : 'Check In Photo'
                    }
                    description="Take a photo to record your attendance."
                    onClose={() => setCameraMode(null)}
                    onPhotoCaptured={handlePhotoCaptured}
                />
            </div>
        </>
    );
}

AttendanceIndex.layout = {
    breadcrumbs: [
        {
            title: 'Attendance',
            href: index(),
        },
    ],
};