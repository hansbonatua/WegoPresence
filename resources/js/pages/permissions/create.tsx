import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { index, store } from '@/routes/permissions';
import type { PermissionType } from '@/types';

type CreatePermissionForm = {
    type: PermissionType;
    start_date: string;
    end_date: string;
    reason: string;
};

const typeOptions: PermissionType[] = ['personal', 'official'];

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

export default function CreatePermission() {
    const form = useForm<CreatePermissionForm>({
        type: 'personal',
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
            <Head title="Add Permission" />

            <div className="flex flex-col gap-6">
                <Heading
                    title="Add Permission"
                    description="Submit a permission request for review"
                />

                <form
                    onSubmit={handleSubmit}
                    className="space-y-8"
                >
                    <section className="grid gap-6 rounded-lg border bg-card p-6">
                        <h2 className="text-base font-semibold">
                            Request details
                        </h2>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="type">Type</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'type',
                                            value as PermissionType,
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        id="type"
                                        className="w-full"
                                        aria-invalid={!!form.errors.type}
                                    >
                                        <SelectValue placeholder="Select a type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {typeOptions.map((type) => (
                                            <SelectItem
                                                key={type}
                                                value={type}
                                            >
                                                {capitalize(type)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError
                                    message={form.errors.type}
                                    className="mt-1"
                                />
                            </div>

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
                                    placeholder="Explain the reason for your permission request"
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

                        <Button
                            type="submit"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            Submit request
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

CreatePermission.layout = {
    breadcrumbs: [
        {
            title: 'Permissions',
            href: index(),
        },
        {
            title: 'Add Permission',
            href: store().url,
        },
    ],
};
