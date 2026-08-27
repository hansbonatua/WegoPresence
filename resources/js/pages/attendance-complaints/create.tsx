import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/attendance-complaints';
import type { AttendanceOption } from '@/types';

type CreateComplaintForm = {
    attendance_id: string;
    complaint_reason: string;
};

type CreateProps = {
    attendances: AttendanceOption[];
};

export default function CreateAttendanceComplaint({
    attendances,
}: CreateProps) {
    const form = useForm<CreateComplaintForm>({
        attendance_id: '',
        complaint_reason: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        form.post(store().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Add Attendance Complaint" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Add Attendance Complaint"
                    description="Report an issue with one of your attendance records"
                />

                <form onSubmit={handleSubmit} className="space-y-8">
                    <section className="grid gap-6 rounded-lg border bg-card p-6">
                        <h2 className="text-base font-semibold">
                            Complaint details
                        </h2>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="attendance_id">
                                    Attendance record
                                </Label>
                                <Select
                                    value={form.data.attendance_id}
                                    onValueChange={(value) =>
                                        form.setData('attendance_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        id="attendance_id"
                                        className="w-full"
                                        aria-invalid={!!form.errors.attendance_id}
                                    >
                                        <SelectValue placeholder="Select the attendance record" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {attendances.length === 0 ? (
                                            <SelectItem value="" disabled>
                                                No attendance records yet
                                            </SelectItem>
                                        ) : (
                                            attendances.map((attendance) => (
                                                <SelectItem
                                                    key={attendance.id}
                                                    value={String(
                                                        attendance.id,
                                                    )}
                                                >
                                                    {attendance.attendance_date}{' '}
                                                    · {attendance.check_in_time ?? '—'}
                                                    {' / '}
                                                    {attendance.check_out_time ?? '—'}{' '}
                                                    ·{' '}
                                                    {attendance.attendance_status ??
                                                        '—'}
                                                </SelectItem>
                                            ))
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.attendance_id}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="complaint_reason">
                                    Complaint reason
                                </Label>
                                <textarea
                                    id="complaint_reason"
                                    value={form.data.complaint_reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'complaint_reason',
                                            event.target.value,
                                        )
                                    }
                                    rows={4}
                                    placeholder="Explain what went wrong with your attendance record"
                                    aria-invalid={!!form.errors.complaint_reason}
                                    className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive md:text-sm"
                                />
                                <InputError
                                    message={form.errors.complaint_reason}
                                    className="mt-1"
                                />
                            </div>
                        </div>
                    </section>

                    <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <Button type="button" variant="secondary" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>

                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Submit complaint
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateAttendanceComplaint.layout = {
    breadcrumbs: [
        {
            title: 'Attendance Complaints',
            href: index(),
        },
        {
            title: 'Add Attendance Complaint',
            href: store().url,
        },
    ],
};