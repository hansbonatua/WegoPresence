import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/business-trips';

type CreateBusinessTripForm = {
    start_date: string;
    end_date: string;
    destination: string;
    purpose: string;
    notes: string;
};

export default function CreateBusinessTrip() {
    const form = useForm<CreateBusinessTripForm>({
        start_date: '',
        end_date: '',
        destination: '',
        purpose: '',
        notes: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        form.post(store().url, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Add Business Trip" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Add Business Trip"
                    description="Submit a business trip request for review"
                />

                <form onSubmit={handleSubmit} className="space-y-8">
                    <section className="grid gap-6 rounded-lg border bg-card p-6">
                        <h2 className="text-base font-semibold">
                            Trip details
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
                                <Label htmlFor="destination">
                                    Destination
                                </Label>
                                <Input
                                    id="destination"
                                    value={form.data.destination}
                                    onChange={(event) =>
                                        form.setData(
                                            'destination',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="e.g. Bandung Branch Office"
                                    aria-invalid={!!form.errors.destination}
                                />
                                <InputError
                                    message={form.errors.destination}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="purpose">Purpose</Label>
                                <textarea
                                    id="purpose"
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            'purpose',
                                            event.target.value,
                                        )
                                    }
                                    rows={3}
                                    placeholder="Explain the purpose of your business trip"
                                    aria-invalid={!!form.errors.purpose}
                                    className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive md:text-sm"
                                />
                                <InputError
                                    message={form.errors.purpose}
                                    className="mt-1"
                                />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="notes">
                                    Notes{' '}
                                    <span className="text-muted-foreground">
                                        (optional)
                                    </span>
                                </Label>
                                <textarea
                                    id="notes"
                                    value={form.data.notes}
                                    onChange={(event) =>
                                        form.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    rows={3}
                                    placeholder="Any additional information"
                                    aria-invalid={!!form.errors.notes}
                                    className="border-input file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground flex w-full min-w-0 rounded-md border bg-transparent px-3 py-2 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive md:text-sm"
                                />
                                <InputError
                                    message={form.errors.notes}
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

CreateBusinessTrip.layout = {
    breadcrumbs: [
        {
            title: 'Business Trips',
            href: index(),
        },
        {
            title: 'Add Business Trip',
            href: store().url,
        },
    ],
};
