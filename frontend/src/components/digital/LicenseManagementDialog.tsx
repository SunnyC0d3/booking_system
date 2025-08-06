'use client';

import * as React from 'react';
import { Monitor } from 'lucide-react';
import {
    Card,
    CardContent,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui';

interface LicenseKey {
    id: number;
    key: string;
    product: {
        id: number;
        name: string;
        latest_version: string;
    };
    status: string;
    created_at: string;
    activations?: Array<{
        id: number;
        device_name?: string;
        activated_at: string;
    }>;
    validation_count?: number;
    last_used_at?: string;
}

interface LicenseManagementDialogProps {
    licenseKey: LicenseKey;
}

export const LicenseManagementDialog: React.FC<LicenseManagementDialogProps> = ({ licenseKey }) => {
    return (
        <Tabs defaultValue="details" className="w-full">
            <TabsList className="grid w-full grid-cols-3">
                <TabsTrigger value="details">Details</TabsTrigger>
                <TabsTrigger value="activations">Activations</TabsTrigger>
                <TabsTrigger value="usage">Usage</TabsTrigger>
            </TabsList>

            <TabsContent value="details" className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="text-sm font-medium">Product</label>
                        <p className="text-sm">{licenseKey.product.name}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium">Version</label>
                        <p className="text-sm">{licenseKey.product.latest_version}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium">Issued Date</label>
                        <p className="text-sm">{new Date(licenseKey.created_at).toLocaleDateString()}</p>
                    </div>
                    <div>
                        <label className="text-sm font-medium">Status</label>
                        <p className="text-sm capitalize">{licenseKey.status}</p>
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="activations" className="space-y-4">
                <div className="space-y-2">
                    <h4 className="font-medium">Active Devices</h4>
                    {licenseKey.activations && licenseKey.activations.length > 0 ? (
                        <div className="space-y-2">
                            {licenseKey.activations.map((activation, index) => (
                                <Card key={activation.id || index}>
                                    <CardContent className="p-3">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Monitor className="h-4 w-4" />
                                                <span className="text-sm">
                                                    {activation.device_name || `Device ${index + 1}`}
                                                </span>
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {new Date(activation.activated_at).toLocaleDateString()}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">No active devices</p>
                    )}
                </div>
            </TabsContent>

            <TabsContent value="usage" className="space-y-4">
                <div className="space-y-4">
                    <div>
                        <h4 className="font-medium mb-2">Usage Statistics</h4>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p className="text-muted-foreground">Total Validations</p>
                                <p className="font-medium">{licenseKey.validation_count || 0}</p>
                            </div>
                            <div>
                                <p className="text-muted-foreground">Last Used</p>
                                <p className="font-medium">
                                    {licenseKey.last_used_at
                                        ? new Date(licenseKey.last_used_at).toLocaleDateString()
                                        : 'Never'
                                    }
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    );
};

export default LicenseManagementDialog;