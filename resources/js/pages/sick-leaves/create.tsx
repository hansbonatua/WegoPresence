import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/sick-leaves';

type CreateSickLeaveForm = {
    start_date: string;
    end_date: string;
    reason: string;
};

export default function CreateSickLeave() {
    const form = useForm<CreateSickLeaveForm>({
        start_date: '',
        end_date: '',
        reason: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        form.post(store().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Add Sick Leave" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Add Sick Leave"
                    description="Submit a sick leave request for review"
                />

                <form onSubmit={handleSubmit} className="space-y-8">
                    <section className="grid gap-6 rounded-lg border bg-card p-6">
                        <h2 className="text-base font-semibold">
                            Sick leave details
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="start_date">Start date</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={form.data.start_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'start_date',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.start_date}
                                />
                                <InputError
                                    message={form.errors.start_date}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="end_date">End date</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={form.data.end_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'end_date',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={!!form.errors.end_date}
                                />
                                <InputError
                                    message={form.errors.end_date}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="reason">Reason</Label>
                                <textarea
                                    id="reason"
                                    value={form.data.reason}
                                    onChange={(event) =>
                                        form.setData(
                                            'reason',
                                            event.target.value,
                                        )
                                    }
                                    rows={4}
                                    placeholder="Explain the reason for your sick leave request"
                                    aria-invalid={!!form.errors.reason}
                                    className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive md:text-sm"
                                />
                                <InputError
                                    message={form.errors.reason}
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
                            Submit request
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreateSickLeave.layout = {
    breadcrumbs: [
        {
            title: 'Sick Leave',
            href: index(),
        },
        {
            title: 'Add Sick Leave',
            href: store().url,
        },
    ],
};
