import { Link, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { index } from '@/routes/users';
import type {
    OfficeResource,
    RoleResource,
    UserResource,
    UserStatus,
} from '@/types';

type UserFormData = {
    role_id: string;
    office_id: string;
    nip: string;
    name: string;
    position: string;
    email: string;
    phone: string;
    join_date: string;
    city: string;
    status: UserStatus;
    password: string;
    password_confirmation: string;
};

type UserFormProps = {
    mode: 'create' | 'edit';
    user?: UserResource;
    roles: RoleResource[];
    offices: OfficeResource[];
    action: string;
    method: 'post' | 'put';
};

const statusOptions: UserStatus[] = ['pending', 'active', 'rejected'];

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function Field({
    label,
    htmlFor,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={htmlFor}>{label}</Label>
            {children}
            <InputError message={error} className="mt-1" />
        </div>
    );
}

export default function UserForm({
    mode,
    user,
    roles,
    offices,
    action,
    method,
}: UserFormProps) {
    const isEdit = mode === 'edit';

    const form = useForm<UserFormData>({
        role_id: user ? String(user.role_id) : '',
        office_id: user ? String(user.office_id) : '',
        nip: user?.nip ?? '',
        name: user?.name ?? '',
        position: user?.position ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        join_date: user?.join_date ?? '',
        city: user?.city ?? '',
        status: user?.status ?? 'pending',
        password: '',
        password_confirmation: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            role_id: Number(data.role_id),
            office_id: Number(data.office_id),
        }));

        if (method === 'put') {
            form.put(action, { preserveScroll: true });
        } else {
            form.post(action, { preserveScroll: true });
        }
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-8">
            <section className="grid gap-6 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">Account information</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Role"
                        htmlFor="role_id"
                        error={form.errors.role_id}
                    >
                        <Select
                            value={form.data.role_id}
                            onValueChange={(value) =>
                                form.setData('role_id', value)
                            }
                        >
                            <SelectTrigger
                                id="role_id"
                                className="w-full"
                                aria-invalid={!!form.errors.role_id}
                            >
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                {roles.map((role) => (
                                    <SelectItem
                                        key={role.id}
                                        value={String(role.id)}
                                    >
                                        {capitalize(role.name)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field
                        label="Office"
                        htmlFor="office_id"
                        error={form.errors.office_id}
                    >
                        <Select
                            value={form.data.office_id}
                            onValueChange={(value) =>
                                form.setData('office_id', value)
                            }
                        >
                            <SelectTrigger
                                id="office_id"
                                className="w-full"
                                aria-invalid={!!form.errors.office_id}
                            >
                                <SelectValue placeholder="Select an office" />
                            </SelectTrigger>
                            <SelectContent>
                                {offices.map((office) => (
                                    <SelectItem
                                        key={office.id}
                                        value={String(office.id)}
                                    >
                                        {office.office_name} ({office.office_code})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field
                        label="Status"
                        htmlFor="status"
                        error={form.errors.status}
                    >
                        <Select
                            value={form.data.status}
                            onValueChange={(value) =>
                                form.setData(
                                    'status',
                                    value as UserStatus,
                                )
                            }
                        >
                            <SelectTrigger
                                id="status"
                                className="w-full"
                                aria-invalid={!!form.errors.status}
                            >
                                <SelectValue placeholder="Select a status" />
                            </SelectTrigger>
                            <SelectContent>
                                {statusOptions.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {capitalize(status)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>

                    <Field
                        label="Join date"
                        htmlFor="join_date"
                        error={form.errors.join_date}
                    >
                        <Input
                            id="join_date"
                            type="date"
                            value={form.data.join_date}
                            onChange={(event) =>
                                form.setData('join_date', event.target.value)
                            }
                        />
                    </Field>
                </div>
            </section>

            <section className="grid gap-6 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">Personal information</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="NIP" htmlFor="nip" error={form.errors.nip}>
                        <Input
                            id="nip"
                            value={form.data.nip}
                            onChange={(event) =>
                                form.setData('nip', event.target.value)
                            }
                            placeholder="e.g. 010226"
                        />
                    </Field>

                    <Field
                        label="Name"
                        htmlFor="name"
                        error={form.errors.name}
                    >
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            placeholder="Full name"
                        />
                    </Field>

                    <Field
                        label="Position"
                        htmlFor="position"
                        error={form.errors.position}
                    >
                        <Input
                            id="position"
                            value={form.data.position}
                            onChange={(event) =>
                                form.setData('position', event.target.value)
                            }
                            placeholder="Job title"
                        />
                    </Field>

                    <Field
                        label="Email"
                        htmlFor="email"
                        error={form.errors.email}
                    >
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            placeholder="email@example.com"
                        />
                    </Field>

                    <Field
                        label="Phone"
                        htmlFor="phone"
                        error={form.errors.phone}
                    >
                        <Input
                            id="phone"
                            type="tel"
                            value={form.data.phone}
                            onChange={(event) =>
                                form.setData('phone', event.target.value)
                            }
                            placeholder="Phone number"
                        />
                    </Field>

                    <Field
                        label="City"
                        htmlFor="city"
                        error={form.errors.city}
                    >
                        <Input
                            id="city"
                            value={form.data.city}
                            onChange={(event) =>
                                form.setData('city', event.target.value)
                            }
                            placeholder="City"
                        />
                    </Field>
                </div>
            </section>

            <section className="grid gap-6 rounded-lg border bg-card p-6">
                <h2 className="text-base font-semibold">Security</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Password"
                        htmlFor="password"
                        error={form.errors.password}
                    >
                        <PasswordInput
                            id="password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            autoComplete={
                                isEdit ? 'new-password' : 'new-password'
                            }
                            placeholder={
                                isEdit
                                    ? 'Leave blank to keep current password'
                                    : 'Minimum 8 characters'
                            }
                        />
                    </Field>

                    <Field
                        label="Confirm password"
                        htmlFor="password_confirmation"
                        error={form.errors.password_confirmation}
                    >
                        <PasswordInput
                            id="password_confirmation"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            autoComplete="new-password"
                            placeholder="Re-enter password"
                        />
                    </Field>
                </div>
            </section>

            <div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Button type="button" variant="secondary" asChild>
                    <Link href={index()}>Cancel</Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing && <Spinner />}
                    {isEdit ? 'Save changes' : 'Create user'}
                </Button>
            </div>
        </form>
    );
}
