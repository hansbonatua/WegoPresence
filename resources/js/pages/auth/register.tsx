import { Form, Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
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
import { store } from '@/routes/register';
import type { OfficeResource } from '@/types';

type Props = {
    offices: OfficeResource[];
};

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
            <InputError message={error} />
        </div>
    );
}

export default function Register({ offices }: Props) {
    return (
        <>
            <Head title="Register" />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <Field
                                label="Office"
                                htmlFor="office_id"
                                error={errors.office_id}
                            >
                                <Select name="office_id">
                                    <SelectTrigger
                                        id="office_id"
                                        className="w-full"
                                        aria-invalid={!!errors.office_id}
                                    >
                                        <SelectValue placeholder="Select your office" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {offices.map((office) => (
                                            <SelectItem
                                                key={office.id}
                                                value={String(office.id)}
                                            >
                                                {office.office_name} (
                                                {office.office_code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="NIP"
                                    htmlFor="nip"
                                    error={errors.nip}
                                >
                                    <Input
                                        id="nip"
                                        name="nip"
                                        autoComplete="off"
                                        placeholder="e.g. 010226"
                                    />
                                </Field>

                                <Field
                                    label="Name"
                                    htmlFor="name"
                                    error={errors.name}
                                >
                                    <Input
                                        id="name"
                                        name="name"
                                        autoComplete="name"
                                        placeholder="Full name"
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Position"
                                    htmlFor="position"
                                    error={errors.position}
                                >
                                    <Input
                                        id="position"
                                        name="position"
                                        placeholder="Job title"
                                    />
                                </Field>

                                <Field
                                    label="Email"
                                    htmlFor="email"
                                    error={errors.email}
                                >
                                    <Input
                                        id="email"
                                        type="email"
                                        name="email"
                                        autoComplete="email"
                                        placeholder="email@example.com"
                                    />
                                </Field>

                                <Field
                                    label="Phone"
                                    htmlFor="phone"
                                    error={errors.phone}
                                >
                                    <Input
                                        id="phone"
                                        type="tel"
                                        name="phone"
                                        autoComplete="tel"
                                        placeholder="Phone number"
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Join date"
                                    htmlFor="join_date"
                                    error={errors.join_date}
                                >
                                    <Input
                                        id="join_date"
                                        type="date"
                                        name="join_date"
                                    />
                                </Field>

                                <Field
                                    label="City"
                                    htmlFor="city"
                                    error={errors.city}
                                >
                                    <Input
                                        id="city"
                                        name="city"
                                        placeholder="City"
                                    />
                                </Field>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Password"
                                    htmlFor="password"
                                    error={errors.password}
                                >
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        autoComplete="new-password"
                                        placeholder="Minimum 8 characters"
                                    />
                                </Field>

                                <Field
                                    label="Confirm password"
                                    htmlFor="password_confirmation"
                                    error={errors.password_confirmation}
                                >
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        placeholder="Re-enter password"
                                    />
                                </Field>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="register-button"
                            >
                                {processing && <Spinner />}
                                Register
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <div className="mt-4 text-center text-sm text-muted-foreground">
                Already have an account?{' '}
                <TextLink href="/login">Sign in</TextLink>
            </div>
        </>
    );
}

Register.layout = {
    title: 'Create your account',
    description: 'Enter your details below to request an account',
};
