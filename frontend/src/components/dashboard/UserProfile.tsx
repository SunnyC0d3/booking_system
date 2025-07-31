'use client'

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    User,
    Mail,
    Phone,
    MapPin,
    Save,
    Camera,
    Shield,
    Bell,
    CreditCard,
    Key,
} from 'lucide-react';
import {
    Button,
    Input,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui';
import { useAuth } from '@/stores/authStore';
import { User as UserType } from '@/types/auth';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

// Profile update schema
const profileUpdateSchema = z.object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    email: z.string().email('Please enter a valid email address'),
    phone: z.string().optional(),
    company: z.string().optional(),
});

type ProfileUpdateData = z.infer<typeof profileUpdateSchema>;

// Password change schema
const passwordChangeSchema = z.object({
    current_password: z.string().min(1, 'Current password is required'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
    message: "Passwords don't match",
    path: ["password_confirmation"],
});

type PasswordChangeData = z.infer<typeof passwordChangeSchema>;

// User Profile Card Component
interface UserProfileCardProps {
    user: UserType;
    className?: string;
}

export const UserProfileCard: React.FC<UserProfileCardProps> = ({
                                                                    user,
                                                                    className,
                                                                }) => {
    const { updateUser } = useAuth();
    const [isEditing, setIsEditing] = React.useState(false);
    const [isLoading, setIsLoading] = React.useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors },
        reset,
    } = useForm<ProfileUpdateData>({
        resolver: zodResolver(profileUpdateSchema),
        defaultValues: {
            name: user.name || '',
            email: user.email || '',
            phone: user.phone || '',
            company: user.company || '',
        },
    });

    React.useEffect(() => {
        reset({
            name: user.name || '',
            email: user.email || '',
            phone: user.phone || '',
            company: user.company || '',
        });
    }, [user, reset]);

    const onSubmit = async (data: ProfileUpdateData) => {
        setIsLoading(true);
        try {
            // Here you would call your API to update user profile
            // await userApi.updateProfile(data);

            // For now, just update the user in the auth store
            updateUser(data);

            setIsEditing(false);
            toast.success('Profile updated successfully');
        } catch (error: any) {
            toast.error(error.message || 'Failed to update profile');
        } finally {
            setIsLoading(false);
        }
    };

    const handleCancel = () => {
        reset();
        setIsEditing(false);
    };

    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <User className="h-5 w-5" />
                        Profile Information
                    </CardTitle>
                    {!isEditing && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setIsEditing(true)}
                        >
                            Edit Profile
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent>
                {isEditing ? (
                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Input
                                {...register('name')}
                                label="Full Name"
                                placeholder="Enter your full name"
                                leftIcon={<User className="h-4 w-4" />}
                                error={errors.name?.message}
                                required
                            />
                            <Input
                                {...register('email')}
                                type="email"
                                label="Email Address"
                                placeholder="Enter your email"
                                leftIcon={<Mail className="h-4 w-4" />}
                                error={errors.email?.message}
                                required
                            />
                            <Input
                                {...register('phone')}
                                type="tel"
                                label="Phone Number"
                                placeholder="Enter your phone number"
                                leftIcon={<Phone className="h-4 w-4" />}
                                error={errors.phone?.message}
                            />
                            <Input
                                {...register('company')}
                                label="Company (Optional)"
                                placeholder="Enter your company name"
                                error={errors.company?.message}
                            />
                        </div>

                        <div className="flex gap-3 pt-4">
                            <Button
                                type="submit"
                                disabled={isLoading}
                                className="flex-1"
                            >
                                <Save className="h-4 w-4 mr-2" />
                                {isLoading ? 'Saving...' : 'Save Changes'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleCancel}
                                disabled={isLoading}
                                className="flex-1"
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                ) : (
                    <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Full Name
                                </label>
                                <p className="text-foreground font-medium">
                                    {user.name || 'Not provided'}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Email Address
                                </label>
                                <p className="text-foreground font-medium">
                                    {user.email}
                                </p>
                                {!user.email_verified_at && (
                                    <p className="text-warning text-xs mt-1">
                                        Email not verified
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Phone Number
                                </label>
                                <p className="text-foreground font-medium">
                                    {user.phone || 'Not provided'}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Company
                                </label>
                                <p className="text-foreground font-medium">
                                    {user.company || 'Not provided'}
                                </p>
                            </div>
                        </div>

                        <div className="pt-4 border-t">
                            <div className="text-sm text-muted-foreground">
                                <p>Member since: {new Date(user.created_at).toLocaleDateString()}</p>
                                {user.last_login_at && (
                                    <p>Last login: {new Date(user.last_login_at).toLocaleDateString()}</p>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

// Password Change Dialog Component
export const PasswordChangeDialog: React.FC = () => {
    const [isOpen, setIsOpen] = React.useState(false);
    const [isLoading, setIsLoading] = React.useState(false);

    const {
        register,
        handleSubmit,
        formState: { errors },
        reset,
    } = useForm<PasswordChangeData>({
        resolver: zodResolver(passwordChangeSchema),
    });

    const onSubmit = async (data: PasswordChangeData) => {
        setIsLoading(true);
        try {
            // Here you would call your API to change password
            // await authApi.changePassword(data);

            reset();
            setIsOpen(false);
            toast.success('Password changed successfully');
        } catch (error: any) {
            toast.error(error.message || 'Failed to change password');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" className="w-full">
                    <Key className="h-4 w-4 mr-2" />
                    Change Password
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Change Password</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    <Input
                        {...register('current_password')}
                        type="password"
                        label="Current Password"
                        placeholder="Enter your current password"
                        error={errors.current_password?.message}
                        required
                    />
                    <Input
                        {...register('password')}
                        type="password"
                        label="New Password"
                        placeholder="Enter your new password"
                        error={errors.password?.message}
                        required
                    />
                    <Input
                        {...register('password_confirmation')}
                        type="password"
                        label="Confirm New Password"
                        placeholder="Confirm your new password"
                        error={errors.password_confirmation?.message}
                        required
                    />

                    <div className="flex gap-3 pt-4">
                        <Button
                            type="submit"
                            disabled={isLoading}
                            className="flex-1"
                        >
                            {isLoading ? 'Changing...' : 'Change Password'}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setIsOpen(false)}
                            disabled={isLoading}
                            className="flex-1"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
};

// Account Security Card Component
export const AccountSecurityCard: React.FC<{ className?: string }> = ({ className }) => {
    const { user } = useAuth();

    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Shield className="h-5 w-5" />
                    Account Security
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">Password</p>
                            <p className="text-sm text-muted-foreground">
                                Last changed {user?.password_changed_at
                                ? new Date(user.password_changed_at).toLocaleDateString()
                                : 'never'
                            }
                            </p>
                        </div>
                        <PasswordChangeDialog />
                    </div>

                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">Two-Factor Authentication</p>
                            <p className="text-sm text-muted-foreground">
                                Add an extra layer of security
                            </p>
                        </div>
                        <Button variant="outline" size="sm" disabled>
                            Enable 2FA
                        </Button>
                    </div>

                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">Email Verification</p>
                            <p className="text-sm text-muted-foreground">
                                {user?.email_verified_at ? 'Verified' : 'Not verified'}
                            </p>
                        </div>
                        {!user?.email_verified_at && (
                            <Button variant="outline" size="sm">
                                Verify Email
                            </Button>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};

// Preferences Card Component
export const PreferencesCard: React.FC<{ className?: string }> = ({ className }) => {
    return (
        <Card className={className}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Bell className="h-5 w-5" />
                    Preferences
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">Email Notifications</p>
                            <p className="text-sm text-muted-foreground">
                                Order updates and promotions
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            defaultChecked
                            className="rounded border-input text-primary focus:ring-primary"
                        />
                    </div>

                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">SMS Notifications</p>
                            <p className="text-sm text-muted-foreground">
                                Delivery updates only
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            className="rounded border-input text-primary focus:ring-primary"
                        />
                    </div>

                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">Marketing Communications</p>
                            <p className="text-sm text-muted-foreground">
                                New products and special offers
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            defaultChecked
                            className="rounded border-input text-primary focus:ring-primary"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};