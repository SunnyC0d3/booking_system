'use client';

import * as React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { motion } from 'framer-motion';
import {
    Package,
    Truck,
    CheckCircle,
    Clock,
    AlertCircle,
    Eye,
    Download,
    RotateCcw,
    Star,
    Search,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    Input,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui';
import { Order } from '@/types/api';
import { cn } from '@/lib/cn';
import { toast } from 'sonner';

// Order Status Configuration
const orderStatuses = {
    pending: {
        label: 'Pending',
        color: 'text-yellow-600 bg-yellow-100',
        icon: Clock,
    },
    confirmed: {
        label: 'Confirmed',
        color: 'text-blue-600 bg-blue-100',
        icon: CheckCircle,
    },
    processing: {
        label: 'Processing',
        color: 'text-purple-600 bg-purple-100',
        icon: Package,
    },
    shipped: {
        label: 'Shipped',
        color: 'text-orange-600 bg-orange-100',
        icon: Truck,
    },
    delivered: {
        label: 'Delivered',
        color: 'text-green-600 bg-green-100',
        icon: CheckCircle,
    },
    cancelled: {
        label: 'Cancelled',
        color: 'text-red-600 bg-red-100',
        icon: AlertCircle,
    },
};

// Order Card Component
interface OrderCardProps {
    order: Order;
    onViewDetails?: (order: Order) => void;
    onReorder?: (order: Order) => void;
    onTrackOrder?: (order: Order) => void;
    className?: string;
}

export const OrderCard: React.FC<OrderCardProps> = ({
                                                        order,
                                                        onViewDetails,
                                                        onReorder,
                                                        onTrackOrder,
                                                        className,
                                                    }) => {
    const statusConfig = orderStatuses[order.status?.name as keyof typeof orderStatuses] || orderStatuses.pending;
    const StatusIcon = statusConfig.icon;

    const handleReorder = () => {
        if (onReorder) {
            onReorder(order);
        } else {
            toast.success('Items added to cart for reordering');
        }
    };

    const handleTrackOrder = () => {
        if (onTrackOrder) {
            onTrackOrder(order);
        } else {
            // Default tracking behavior
            window.open(`/orders/${order.id}/tracking`, '_blank');
        }
    };

    return (
        <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            className={className}
        >
            <Card className="hover:shadow-md transition-shadow">
                <CardContent className="p-6">
                    <div className="flex items-start justify-between mb-4">
                        <div>
                            <h3 className="font-semibold text-lg text-foreground">
                                Order #{order.id}
                            </h3>
                            <p className="text-sm text-muted-foreground">
                                Placed on {new Date(order.created_at).toLocaleDateString()}
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <div className={cn(
                                'flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium',
                                statusConfig.color
                            )}>
                                <StatusIcon className="h-3 w-3" />
                                {statusConfig.label}
                            </div>
                        </div>
                    </div>

                    {/* Order Items Preview */}
                    <div className="space-y-3 mb-4">
                        {order.order_items?.slice(0, 3).map((item) => (
                            <div key={item.id} className="flex items-center gap-3">
                                <div className="relative w-12 h-12 bg-muted rounded-lg overflow-hidden">
                                    {item.product?.featured_image ? (
                                        <Image
                                            src={item.product.featured_image}
                                            alt={item.product.name || 'Product'}
                                            fill
                                            className="object-cover"
                                        />
                                    ) : (
                                        <div className="w-full h-full flex items-center justify-center">
                                            <Package className="h-4 w-4 text-muted-foreground" />
                                        </div>
                                    )}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="font-medium text-sm truncate">
                                        {item.product?.name || 'Unknown Product'}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Qty: {item.quantity} × {item.price_formatted}
                                    </p>
                                </div>
                                <div className="text-sm font-medium">
                                    {item.line_total_formatted}
                                </div>
                            </div>
                        ))}

                        {order.order_items && order.order_items.length > 3 && (
                            <p className="text-xs text-muted-foreground text-center py-2">
                                +{order.order_items.length - 3} more items
                            </p>
                        )}
                    </div>

                    {/* Order Total */}
                    <div className="flex items-center justify-between mb-4 pt-4 border-t">
                        <span className="font-medium">Total:</span>
                        <span className="text-lg font-bold text-primary">
                            {order.total_amount_formatted}
                        </span>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onViewDetails?.(order)}
                            className="flex-1"
                        >
                            <Eye className="h-4 w-4 mr-2" />
                            View Details
                        </Button>

                        {order.status?.name === 'shipped' && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleTrackOrder}
                                className="flex-1"
                            >
                                <Truck className="h-4 w-4 mr-2" />
                                Track Order
                            </Button>
                        )}

                        {(order.status?.name === 'delivered' || order.status?.name === 'cancelled') && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleReorder}
                                className="flex-1"
                            >
                                <RotateCcw className="h-4 w-4 mr-2" />
                                Reorder
                            </Button>
                        )}
                    </div>
                </CardContent>
            </Card>
        </motion.div>
    );
};

// Order Details Dialog Component
interface OrderDetailsDialogProps {
    order: Order | null;
    isOpen: boolean;
    onClose: () => void;
}

export const OrderDetailsDialog: React.FC<OrderDetailsDialogProps> = ({
                                                                          order,
                                                                          isOpen,
                                                                          onClose,
                                                                      }) => {
    if (!order) return null;

    const statusConfig = orderStatuses[order.status?.name as keyof typeof orderStatuses] || orderStatuses.pending;
    const StatusIcon = statusConfig.icon;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Package className="h-5 w-5" />
                        Order #{order.id} Details
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Order Status & Info */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="text-sm font-medium text-muted-foreground">Status</label>
                            <div className={cn(
                                'flex items-center gap-2 mt-1 px-3 py-1 rounded-lg text-sm font-medium w-fit',
                                statusConfig.color
                            )}>
                                <StatusIcon className="h-4 w-4" />
                                {statusConfig.label}
                            </div>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-muted-foreground">Order Date</label>
                            <p className="text-foreground font-medium">
                                {new Date(order.created_at).toLocaleDateString()}
                            </p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-muted-foreground">Total Amount</label>
                            <p className="text-lg font-bold text-primary">
                                {order.total_amount_formatted}
                            </p>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-muted-foreground">Payment Status</label>
                            <p className="text-foreground font-medium">
                                {order.payments?.[0]?.status || 'Pending'}
                            </p>
                        </div>
                    </div>

                    {/* Order Items */}
                    <div>
                        <h3 className="font-semibold mb-4">Order Items</h3>
                        <div className="space-y-3">
                            {order.order_items?.map((item) => (
                                <div key={item.id} className="flex items-center gap-4 p-3 bg-muted/50 rounded-lg">
                                    <div className="relative w-16 h-16 bg-background rounded-lg overflow-hidden">
                                        {item.product?.featured_image ? (
                                            <Image
                                                src={item.product.featured_image}
                                                alt={item.product.name || 'Product'}
                                                fill
                                                className="object-cover"
                                            />
                                        ) : (
                                            <div className="w-full h-full flex items-center justify-center">
                                                <Package className="h-6 w-6 text-muted-foreground" />
                                            </div>
                                        )}
                                    </div>
                                    <div className="flex-1">
                                        <h4 className="font-medium">
                                            {item.product?.name || 'Unknown Product'}
                                        </h4>
                                        {item.product_variant && (
                                            <p className="text-sm text-muted-foreground">
                                                {item.product_variant.product_attribute?.name}: {item.product_variant.value}
                                            </p>
                                        )}
                                        <p className="text-sm text-muted-foreground">
                                            Quantity: {item.quantity}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium">{item.line_total_formatted}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {item.price_formatted} each
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex gap-3 pt-4 border-t">
                        <Button
                            variant="outline"
                            className="flex-1"
                            onClick={() => {
                                // Download invoice/receipt
                                toast.success('Invoice downloaded');
                            }}
                        >
                            <Download className="h-4 w-4 mr-2" />
                            Download Invoice
                        </Button>

                        {order.status?.name === 'delivered' && (
                            <Button
                                variant="outline"
                                className="flex-1"
                                onClick={() => {
                                    // Leave review
                                    toast.success('Review form opened');
                                }}
                            >
                                <Star className="h-4 w-4 mr-2" />
                                Leave Review
                            </Button>
                        )}
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
};

// Order History List Component
interface OrderHistoryListProps {
    orders: Order[];
    loading?: boolean;
    error?: string | null;
    onRefresh?: () => void;
    className?: string;
}

export const OrderHistoryList: React.FC<OrderHistoryListProps> = ({
                                                                      orders,
                                                                      loading = false,
                                                                      error = null,
                                                                      onRefresh,
                                                                      className,
                                                                  }) => {
    const [selectedOrder, setSelectedOrder] = React.useState<Order | null>(null);
    const [isDetailsOpen, setIsDetailsOpen] = React.useState(false);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState<string>('all');

    // Filter orders based on search and status
    const filteredOrders = React.useMemo(() => {
        return orders.filter(order => {
            const matchesSearch = searchQuery === '' ||
                order.id.toString().includes(searchQuery) ||
                order.order_items?.some(item =>
                    item.product?.name?.toLowerCase().includes(searchQuery.toLowerCase())
                );

            const matchesStatus = statusFilter === 'all' ||
                order.status?.name === statusFilter;

            return matchesSearch && matchesStatus;
        });
    }, [orders, searchQuery, statusFilter]);

    const handleViewDetails = (order: Order) => {
        setSelectedOrder(order);
        setIsDetailsOpen(true);
    };

    const handleReorder = (order: Order) => {
        // Add all items from the order to cart
        toast.success(`${order.order_items?.length || 0} items added to cart`);
    };

    if (loading) {
        return (
            <div className={cn('space-y-4', className)}>
                {Array.from({ length: 3 }).map((_, i) => (
                    <Card key={i} className="animate-pulse">
                        <CardContent className="p-6">
                            <div className="space-y-3">
                                <div className="h-4 bg-muted rounded w-1/4" />
                                <div className="h-3 bg-muted rounded w-1/6" />
                                <div className="space-y-2">
                                    <div className="h-12 bg-muted rounded" />
                                    <div className="h-12 bg-muted rounded" />
                                </div>
                                <div className="flex gap-2">
                                    <div className="h-8 bg-muted rounded flex-1" />
                                    <div className="h-8 bg-muted rounded flex-1" />
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>
        );
    }

    if (error) {
        return (
            <Card className={className}>
                <CardContent className="p-6 text-center">
                    <AlertCircle className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                    <h3 className="text-lg font-semibold mb-2">Failed to Load Orders</h3>
                    <p className="text-muted-foreground mb-4">{error}</p>
                    {onRefresh && (
                        <Button onClick={onRefresh} variant="outline">
                            Try Again
                        </Button>
                    )}
                </CardContent>
            </Card>
        );
    }

    return (
        <div className={cn('space-y-6', className)}>
            {/* Filters */}
            <Card>
                <CardContent className="p-4">
                    <div className="flex flex-col sm:flex-row gap-4">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                            <Input
                                placeholder="Search orders by ID or product name..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="pl-10"
                            />
                        </div>
                        <select
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                            className="px-3 py-2 border border-input bg-background rounded-lg text-sm"
                        >
                            <option value="all">All Orders</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="processing">Processing</option>
                            <option value="shipped">Shipped</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            {/* Orders List */}
            {filteredOrders.length === 0 ? (
                <Card>
                    <CardContent className="p-12 text-center">
                        <Package className="h-16 w-16 text-muted-foreground mx-auto mb-4" />
                        <h3 className="text-xl font-semibold mb-2">No Orders Found</h3>
                        <p className="text-muted-foreground mb-6">
                            {searchQuery || statusFilter !== 'all'
                                ? 'No orders match your current filters.'
                                : 'You haven\'t placed any orders yet.'
                            }
                        </p>
                        <Link href="/products">
                            <Button>
                                Start Shopping
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-4">
                    {filteredOrders.map((order) => (
                        <OrderCard
                            key={order.id}
                            order={order}
                            onViewDetails={handleViewDetails}
                            onReorder={handleReorder}
                        />
                    ))}
                </div>
            )}

            {/* Order Details Dialog */}
            <OrderDetailsDialog
                order={selectedOrder}
                isOpen={isDetailsOpen}
                onClose={() => {
                    setIsDetailsOpen(false);
                    setSelectedOrder(null);
                }}
            />
        </div>
    );
};