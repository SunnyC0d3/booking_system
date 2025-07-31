'use client'

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import {
    MapPin,
    Plus,
    Edit,
    Trash2,
    Check,
    Star,
    Home,
    Building,
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
import { ShippingAddress } from '@/types/api';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

// Address form schema
const addressSchema = z.object({
    type: z.enum(['shipping', 'billing', 'both']),
    name: z.string().min(2, 'Name must be at least 2 characters'),
    company: z.string().optional(),
    line1: z.string().min(5, 'Address line 1 is required'),
    line2: z.string().optional(),
    city: z.string().min(2, 'City is required'),
    county: z.string().optional(),
    postcode: z.string().min(3, 'Postcode is required'),
    country: z.string().min(2, 'Country is required'),
    phone: z.string().optional(),
    is_default: z.boolean().default(false),
});

type AddressFormData = z.infer<typeof addressSchema>;

// Sample addresses data (replace with API call)
const sampleAddresses: ShippingAddress[] = [
    {
        id: 1,
        type: 'both',
        type_label: 'Shipping & Billing',
        name: 'John Smith',
        company: null,
        line1: '123 Creative Street',
        line2: 'Apartment 4B',
        city: 'London',
        county: 'Greater London',
        postcode: 'SW1A 1AA',
        country: 'GB',
        country_name: 'United Kingdom',
        phone: '+44 20 7946 0958',
        is_default: true,
        is_validated: true,
        is_uk_address: true,
        is_international: false,
        full_address: '123 Creative Street, Apartment 4B, London, Greater London, SW1A 1AA, United Kingdom',
        formatted_address: '123 Creative Street\nApartment 4B\nLondon, Greater London SW1A 1AA\nUnited Kingdom',
        normalized_postcode: 'SW1A 1AA',
        needs_validation: false,
        created_at: '2024-01-15T10:30:00.000000Z',
        updated_at: '2024-01-15T10:30:00.000000Z',
    },
    {
        id: 2,
        type: 'shipping',
        type_label: 'Shipping Only',
        name: 'John Smith',
        company: 'Creative Business Ltd',
        line1: '456 Business Avenue',
        line2: null,
        city: 'Manchester',
        county: 'Greater Manchester',
        postcode: 'M1 1AA',
        country: 'GB',
        country_name: 'United Kingdom',
        phone: '+44 161 123 4567',
        is_default: false,
        is_validated: true,
        is_uk_address: true,
        is_international: false,
        full_address: '456 Business Avenue, Manchester, Greater Manchester, M1 1AA, United Kingdom',
        formatted_address: '456 Business Avenue\nManchester, Greater Manchester M1 1AA\nUnited Kingdom',
        normalized_postcode: 'M1 1AA',
        needs_validation: false,
        created_at: '2024-02-10T14:20:00.000000Z',
        updated_at: '2024-02-10T14:20:00.000000Z',
    },
];

// Address Card Component
interface AddressCardProps {
    address: ShippingAddress;
    onEdit?: (address: ShippingAddress) => void;
    onDelete?: (addressId: number) => void;
    onSetDefault?: (addressId: number) => void;
    className?: string;
}

export const AddressCard: React.FC<AddressCardProps> = ({
                                                            address,
                                                            onEdit,
                                                            onDelete,
                                                            onSetDefault,
                                                            className,
                                                        }) => {
    const handleSetDefault = () => {
        if (onSetDefault && !address.is_default) {
            onSetDefault(address.id);
        }
    };

    const handleDelete = () => {
        if (onDelete && !address.is_default) {
            onDelete(address.id);
        }
    };

    return (
        <Card className={cn(
            'relative transition-all duration-200',
            address.is_default && 'ring-2 ring-primary/50 bg-primary/5',
            className
        )}>
            <CardContent className="p-4">
                {/* Header */}
                <div className="flex items-start justify-between mb-3">
                    <div className="flex items-center gap-2">
                        <div className="flex items-center gap-2">
                            {address.company ? (
                                <Building className="h-4 w-4 text-muted-foreground" />
                            ) : (
                                <Home className="h-4 w-4 text-muted-foreground" />
                            )}
                            <span className="font-medium">{address.name}</span>
                        </div>
                        {address.is_default && (
                            <div className="flex items-center gap-1 px-2 py-1 bg-primary text-primary-foreground text-xs rounded-full">
                                <Star className="h-3 w-3" />
                                Default
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => onEdit?.(address)}
                            className="h-8 w-8 p-0"
                        >
                            <Edit className="h-3 w-3" />
                        </Button>
                        {!address.is_default && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={handleDelete}
                                className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                            >
                                <Trash2 className="h-3 w-3" />
                            </Button>
                        )}
                    </div>
                </div>

                {/* Address Type */}
                <div className="text-xs text-muted-foreground mb-2">
                    {address.type_label}
                </div>

                {/* Address Details */}
                <div className="space-y-1 text-sm">
                    {address.company && (
                        <p className="font-medium">{address.company}</p>
                    )}
                    <p>{address.line1}</p>
                    {address.line2 && <p>{address.line2}</p>}
                    <p>{address.city}, {address.county} {address.postcode}</p>
                    <p>{address.country_name}</p>
                    {address.phone && (
                        <p className="text-muted-foreground">{address.phone}</p>
                    )}
                </div>

                {/* Validation Status */}
                {address.needs_validation && (
                    <div className="mt-3 p-2 bg-warning/10 border border-warning/20 rounded text-xs text-warning">
                        Address validation required
                    </div>
                )}

                {/* Actions */}
                {!address.is_default && (
                    <div className="mt-4 pt-3 border-t">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleSetDefault}
                            className="w-full"
                        >
                            <Check className="h-3 w-3 mr-2" />
                            Set as Default
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
    );
};

// Address Form Dialog Component
interface AddressFormDialogProps {
    address?: ShippingAddress | null;
    isOpen: boolean;
    onClose: () => void;
    onSave?: (data: AddressFormData) => void;
}

export const AddressFormDialog: React.FC<AddressFormDialogProps> = ({
                                                                        address,
                                                                        isOpen,
                                                                        onClose,
                                                                        onSave,
                                                                    }) => {
    const [isLoading, setIsLoading] = React.useState(false);
    const isEditing = !!address;

    const {
        register,
        handleSubmit,
        formState: { errors },
        reset,
        watch,
    } = useForm<AddressFormData>({
        resolver: zodResolver(addressSchema),
        defaultValues: {
            type: 'shipping',
            name: '',
            company: '',
            line1: '',
            line2: '',
            city: '',
            county: '',
            postcode: '',
            country: 'GB',
            phone: '',
            is_default: false,
        },
    });

    // Reset form when address changes
    React.useEffect(() => {
        if (address) {
            reset({
                type: address.type,
                name: address.name,
                company: address.company || '',
                line1: address.line1,
                line2: address.line2 || '',
                city: address.city,
                county: address.county || '',
                postcode: address.postcode,
                country: address.country,
                phone: address.phone || '',
                is_default: address.is_default,
            });
        } else {
            reset({
                type: 'shipping',
                name: '',
                company: '',
                line1: '',
                line2: '',
                city: '',
                county: '',
                postcode: '',
                country: 'GB',
                phone: '',
                is_default: false,
            });
        }
    }, [address, reset]);

    const onSubmit = async (data: AddressFormData) => {
        setIsLoading(true);
        try {
            // Here you would call your API to save the address
            // await addressApi.saveAddress(data);

            if (onSave) {
                onSave(data);
            }

            toast.success(isEditing ? 'Address updated successfully' : 'Address added successfully');
            onClose();
        } catch (error: any) {
            toast.error(error.message || 'Failed to save address');
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? 'Edit Address' : 'Add New Address'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
                    {/* Address Type */}
                    <div>
                        <label className="text-sm font-medium mb-2 block">
                            Address Type
                        </label>
                        <select
                            {...register('type')}
                            className="w-full px-3 py-2 border border-input bg-background rounded-lg text-sm"
                        >
                            <option value="shipping">Shipping Only</option>
                            <option value="billing">Billing Only</option>
                            <option value="both">Shipping & Billing</option>
                        </select>
                        {errors.type && (
                            <p className="text-sm text-destructive mt-1">
                                {errors.type.message}
                            </p>
                        )}
                    </div>

                    {/* Name and Company */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Input
                            {...register('name')}
                            label="Full Name"
                            placeholder="Enter full name"
                            error={errors.name?.message}
                            required
                        />
                        <Input
                            {...register('company')}
                            label="Company (Optional)"
                            placeholder="Enter company name"
                            error={errors.company?.message}
                        />
                    </div>

                    {/* Address Lines */}
                    <Input
                        {...register('line1')}
                        label="Address Line 1"
                        placeholder="Street address, P.O. box, company name"
                        error={errors.line1?.message}
                        required
                    />
                    <Input
                        {...register('line2')}
                        label="Address Line 2 (Optional)"
                        placeholder="Apartment, suite, unit, building, floor, etc."
                        error={errors.line2?.message}
                    />

                    {/* City, County, Postcode */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Input
                            {...register('city')}
                            label="City"
                            placeholder="Enter city"
                            error={errors.city?.message}
                            required
                        />
                        <Input
                            {...register('county')}
                            label="County/State"
                            placeholder="Enter county"
                            error={errors.county?.message}
                        />
                        <Input
                            {...register('postcode')}
                            label="Postcode"
                            placeholder="Enter postcode"
                            error={errors.postcode?.message}
                            required
                        />
                    </div>

                    {/* Country and Phone */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="text-sm font-medium mb-2 block">
                                Country
                            </label>
                            <select
                                {...register('country')}
                                className="w-full px-3 py-2 border border-input bg-background rounded-lg text-sm"
                            >
                                <option value="GB">United Kingdom</option>
                                <option value="US">United States</option>
                                <option value="CA">Canada</option>
                                <option value="AU">Australia</option>
                                <option value="DE">Germany</option>
                                <option value="FR">France</option>
                                <option value="ES">Spain</option>
                                <option value="IT">Italy</option>
                            </select>
                            {errors.country && (
                                <p className="text-sm text-destructive mt-1">
                                    {errors.country.message}
                                </p>
                            )}
                        </div>
                        <Input
                            {...register('phone')}
                            type="tel"
                            label="Phone Number (Optional)"
                            placeholder="Enter phone number"
                            error={errors.phone?.message}
                        />
                    </div>

                    {/* Default Address Checkbox */}
                    <div className="flex items-center space-x-2">
                        <input
                            {...register('is_default')}
                            type="checkbox"
                            id="is_default"
                            className="rounded border-input text-primary focus:ring-primary"
                        />
                        <label htmlFor="is_default" className="text-sm font-medium">
                            Set as default address
                        </label>
                    </div>

                    {/* Form Actions */}
                    <div className="flex gap-3 pt-4">
                        <Button
                            type="submit"
                            disabled={isLoading}
                            className="flex-1"
                        >
                            {isLoading
                                ? (isEditing ? 'Updating...' : 'Adding...')
                                : (isEditing ? 'Update Address' : 'Add Address')
                            }
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
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

// Address Management Component
interface AddressManagementProps {
    className?: string;
}

export const AddressManagement: React.FC<AddressManagementProps> = ({ className }) => {
    const [addresses, setAddresses] = React.useState<ShippingAddress[]>(sampleAddresses);
    const [selectedAddress, setSelectedAddress] = React.useState<ShippingAddress | null>(null);
    const [isFormOpen, setIsFormOpen] = React.useState(false);

    const handleAddAddress = () => {
        setSelectedAddress(null);
        setIsFormOpen(true);
    };

    const handleEditAddress = (address: ShippingAddress) => {
        setSelectedAddress(address);
        setIsFormOpen(true);
    };

    const handleDeleteAddress = (addressId: number) => {
        setAddresses(prev => prev.filter(addr => addr.id !== addressId));
        toast.success('Address deleted successfully');
    };

    const handleSetDefault = (addressId: number) => {
        setAddresses(prev => prev.map(addr => ({
            ...addr,
            is_default: addr.id === addressId,
        })));
        toast.success('Default address updated');
    };

    const handleSaveAddress = (data: AddressFormData) => {
        if (selectedAddress) {
            // Update existing address
            setAddresses(prev => prev.map(addr =>
                addr.id === selectedAddress.id
                    ? { ...addr, ...data, updated_at: new Date().toISOString() }
                    : addr
            ));
        } else {
            // Add new address
            const newAddress: ShippingAddress = {
                id: Math.max(...addresses.map(a => a.id)) + 1,
                ...data,
                type_label: data.type === 'both' ? 'Shipping & Billing' :
                    data.type === 'shipping' ? 'Shipping Only' : 'Billing Only',
                country_name: 'United Kingdom', // You'd get this from country code
                is_validated: false,
                is_uk_address: data.country === 'GB',
                is_international: data.country !== 'GB',
                full_address: `${data.line1}, ${data.city}, ${data.postcode}`,
                formatted_address: `${data.line1}\n${data.city} ${data.postcode}`,
                normalized_postcode: data.postcode,
                needs_validation: true,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            };
            setAddresses(prev => [...prev, newAddress]);
        }
    };

    return (
        <div className={className}>
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <CardTitle className="flex items-center gap-2">
                            <MapPin className="h-5 w-5" />
                            Shipping Addresses
                        </CardTitle>
                        <Button onClick={handleAddAddress}>
                            <Plus className="h-4 w-4 mr-2" />
                            Add Address
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    {addresses.length === 0 ? (
                        <div className="text-center py-8">
                            <MapPin className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                            <h3 className="text-lg font-semibold mb-2">No Addresses</h3>
                            <p className="text-muted-foreground mb-4">
                                Add your first shipping address to get started.
                            </p>
                            <Button onClick={handleAddAddress}>
                                <Plus className="h-4 w-4 mr-2" />
                                Add Address
                            </Button>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {addresses.map((address) => (
                                <AddressCard
                                    key={address.id}
                                    address={address}
                                    onEdit={handleEditAddress}
                                    onDelete={handleDeleteAddress}
                                    onSetDefault={handleSetDefault}
                                />
                            ))}
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Address Form Dialog */}
            <AddressFormDialog
                address={selectedAddress}
                isOpen={isFormOpen}
                onClose={() => {
                    setIsFormOpen(false);
                    setSelectedAddress(null);
                }}
                onSave={handleSaveAddress}
            />
        </div>
    );
};