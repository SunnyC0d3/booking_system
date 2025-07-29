import * as React from 'react';
import { useState } from 'react';
import { motion } from 'framer-motion';
import {
    Key,
    Copy,
    Eye,
    EyeOff,
    Shield,
    Calendar,
    Monitor,
    AlertTriangle,
    CheckCircle,
    RefreshCw,
    Settings
} from 'lucide-react';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Button,
    Badge,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
    Input,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger
} from '@/components/ui';
import { cn } from '@/lib/cn';

interface LicenseManagerProps {
    licenseKeys: any[];
    className?: string;
}

export const LicenseManager: React.FC<LicenseManagerProps> = ({
                                                                  licenseKeys,
                                                                  className
                                                              }) => {
    const [showKeys, setShowKeys] = useState<{ [key: string]: boolean }>({});
    const [validatingKey, setValidatingKey] = useState<string | null>(null);

    const toggleKeyVisibility = (keyId: string) => {
        setShowKeys(prev => ({
            ...prev,
            [keyId]: !prev[keyId]
        }));
    };

    const copyToClipboard = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            // You might want to show a toast notification here
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    };

    const validateLicense = async (licenseKey: any) => {
        setValidatingKey(licenseKey.id);
        try {
            const response = await fetch('/api/v1/license/validate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    license_key: licenseKey.key,
                    product_id: licenseKey.product_id
                })
            });

            const data = await response.json();
            // Handle validation result
            console.log('License validation:', data);
        } catch (error) {
            console.error('License validation failed:', error);
        } finally {
            setValidatingKey(null);
        }
    };

    if (licenseKeys.length === 0) {
        return (
            <Card className={className}>
                <CardContent className="p-8 text-center">
                    <Key className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                    <h3 className="text-lg font-semibold mb-2">No License Keys</h3>
                    <p className="text-muted-foreground">
                        License keys will appear here when you purchase licensed software.
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className={cn('space-y-4', className)}>
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold">License Keys</h3>
                <Badge variant="outline">
                    {licenseKeys.filter(key => key.status === 'active').length} Active
                </Badge>
            </div>

            {licenseKeys.map((licenseKey) => (
                <LicenseKeyCard
                    key={licenseKey.id}
                    licenseKey={licenseKey}
                    showKey={showKeys[licenseKey.id] || false}
                    onToggleVisibility={() => toggleKeyVisibility(licenseKey.id)}
                    onCopy={() => copyToClipboard(licenseKey.key)}
                    onValidate={() => validateLicense(licenseKey)}
                    isValidating={validatingKey === licenseKey.id}
                />
            ))}
        </div>
    );
};

// frontend/src/components/digital/LicenseKeyCard.tsx
interface LicenseKeyCardProps {
    licenseKey: any;
    showKey: boolean;
    onToggleVisibility: () => void;
    onCopy: () => void;
    onValidate: () => void;
    isValidating: boolean;
}

const LicenseKeyCard: React.FC<LicenseKeyCardProps> = ({
                                                           licenseKey,
                                                           showKey,
                                                           onToggleVisibility,
                                                           onCopy,
                                                           onValidate,
                                                           isValidating
                                                       }) => {
    const isExpired = licenseKey.expires_at && new Date(licenseKey.expires_at) < new Date();
    const isExpiringSoon = licenseKey.expires_at &&
        new Date(licenseKey.expires_at) < new Date(Date.now() + 30 * 24 * 60 * 60 * 1000);

    const formatKey = (key: string) => {
        if (!showKey) {
            return key.replace(/./g, '•');
        }
        // Format as groups of 4 characters
        return key.match(/.{1,4}/g)?.join('-') || key;
    };

    return (
        <Card className="transition-all hover:shadow-md">
            <CardContent className="p-6">
                <div className="space-y-4">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <div>
                            <h4 className="font-semibold">{licenseKey.product.name}</h4>
                            <p className="text-sm text-muted-foreground">
                                License Type: {licenseKey.license_type || 'Standard'}
                            </p>
                        </div>
                        <div className="flex items-center gap-2">
                            {isExpired ? (
                                <Badge variant="destructive">Expired</Badge>
                            ) : isExpiringSoon ? (
                                <Badge variant="secondary">Expiring Soon</Badge>
                            ) : (
                                <Badge variant="outline" className="text-green-600">
                                    <CheckCircle className="h-3 w-3 mr-1" />
                                    Active
                                </Badge>
                            )}
                        </div>
                    </div>

                    {/* License Key */}
                    <div className="space-y-2">
                        <label className="text-sm font-medium">License Key</label>
                        <div className="flex items-center gap-2">
                            <Input
                                value={formatKey(licenseKey.key)}
                                readOnly
                                className="font-mono text-sm"
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={onToggleVisibility}
                            >
                                {showKey ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={onCopy}
                            >
                                <Copy className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>

                    {/* License Details */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p className="text-muted-foreground">Max Activations</p>
                            <p className="font-medium">{licenseKey.max_activations}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Used</p>
                            <p className="font-medium">{licenseKey.activation_count}</p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Remaining</p>
                            <p className="font-medium">
                                {licenseKey.max_activations - licenseKey.activation_count}
                            </p>
                        </div>
                        <div>
                            <p className="text-muted-foreground">Expires</p>
                            <p className="font-medium">
                                {licenseKey.expires_at
                                    ? new Date(licenseKey.expires_at).toLocaleDateString()
                                    : 'Never'
                                }
                            </p>
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-between pt-2 border-t">
                        <div className="flex items-center gap-2">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={onValidate}
                                disabled={isValidating}
                            >
                                {isValidating ? (
                                    <RefreshCw className="h-4 w-4 mr-2 animate-spin" />
                                ) : (
                                    <Shield className="h-4 w-4 mr-2" />
                                )}
                                Validate
                            </Button>

                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button size="sm" variant="outline">
                                        <Settings className="h-4 w-4 mr-2" />
                                        Manage
                                    </Button>
                                </DialogTrigger>
                                <DialogContent className="max-w-2xl">
                                    <DialogHeader>
                                        <DialogTitle>Manage License: {licenseKey.product.name}</DialogTitle>
                                    </DialogHeader>
                                    <LicenseManagementDialog licenseKey={licenseKey} />
                                </DialogContent>
                            </Dialog>
                        </div>

                        {licenseKey.last_validated_at && (
                            <div className="text-xs text-muted-foreground">
                                Last validated: {new Date(licenseKey.last_validated_at).toLocaleString()}
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
};