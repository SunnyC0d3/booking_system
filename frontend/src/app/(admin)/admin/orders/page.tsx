'use client'

import * as React from 'react';
import { motion } from 'framer-motion';
import {
    ShoppingCart,
    Search,
    Download,
    Eye,
    Edit,
    Truck,
    Clock,
    Package,
    CreditCard,
    User,
    MoreHorizontal,
    RefreshCw,
    Mail,
    FileText,
} from 'lucide-react';
import {
    Button,
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Input,
    Badge,
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    Checkbox,
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui';
import { MainLayout } from '@/components/layout';
import { RouteGuard } from '@/components/auth/RouteGuard';
import { cn } from '@/lib/cn';

// Mock order data - replace with real API
const mockOrders = [
    {
        id: 'ORD-001',
        customer: {
            id: 1,
            name: 'Sarah Johnson',
            email: 'sarah@example.com',
            phone: '+44 20 7123 4567',
            avatar: '',
        },
        status: 'processing',
        payment_status: 'paid',
        total: 125.00,
        items_count: 2,
        shipping_address: {
            line1: '123 Main Street',
            city: 'London',
            postcode: 'SW1A 1AA',
            country: 'UK',
        },
        created_at: '2025-01-28T10:30:00Z',
        updated_at: '2025-01-28T11:15:00Z',
        products: [
            { name: 'Wedding Invitation Set', quantity: 1, price: 45.00 },
            { name: 'Thank You Cards', quantity: 50, price: 80.00 },
        ],
        tracking_number: null,
        notes: 'Customer requested rush delivery',
    },
    {
        id: 'ORD-002',
        customer: {
            id: 2,
            name: 'Michael Chen',
            email: 'michael@example.com',
            phone: '+44 20 7123 4568',
            avatar: '',
        },
        status: 'shipped',
        payment_status: 'paid',
        total: 89.50,
        items_count: 1,
        shipping_address: {
            line1: '456 Business Park',
            city: 'Manchester',
            postcode: 'M1 1AA',
            country: 'UK',
        },
        created_at: '2025-01-27T14:20:00Z',
        updated_at: '2025-01-28T09:30:00Z',
        products: [
            { name: 'Custom Business Labels', quantity: 200, price: 89.50 },
        ],
        tracking_number: 'TRK-123456789',
        notes: '',
    },
];

const orderStats = [
    {
        title: 'Total Orders',
        value: '1,234',
        change: { value: 8, type: 'increase' as const, period: 'from last month' },
        icon: ShoppingCart,
        color: 'blue' as const,
    },
    {
        title: 'Pending Orders',
        value: '47',
        change: { value: 12, type: 'increase' as const, period: 'since yesterday' },
        icon: Clock,
        color: 'orange' as const,
    },
    {
        title: 'Revenue Today',
        value: '£2,456',
        change: { value: 15, type: 'increase' as const, period: 'vs yesterday' },
        icon: CreditCard,
        color: 'green' as const,
    },
    {
        title: 'Avg Order Value',
        value: '£89.50',
        change: { value: 3, type: 'increase' as const, period: 'from last week' },
        icon: Package,
        color: 'purple' as const,
    },
];

const getStatusColor = (status: string) => {
    switch (status) {
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'processing':
            return 'bg-blue-100 text-blue-800';
        case 'shipped':
            return 'bg-purple-100 text-purple-800';
        case 'completed':
            return 'bg-green-100 text-green-800';
        case 'cancelled':
            return 'bg-red-100 text-red-800';
        case 'refunded':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const getPaymentStatusColor = (status: string) => {
    switch (status) {
        case 'paid':
            return 'bg-green-100 text-green-800';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800';
        case 'failed':
            return 'bg-red-100 text-red-800';
        case 'refunded':
            return 'bg-gray-100 text-gray-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-GB', {
        style: 'currency',
        currency: 'GBP',
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// QuickStats component
const QuickStats = ({ stats }: { stats: typeof orderStats }) => (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, index) => (
            <Card key={index}>
                <CardContent className="p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm font-medium text-gray-600">{stat.title}</p>
                            <p className="text-2xl font-bold text-gray-900">{stat.value}</p>
                            <p className="text-xs text-gray-500 mt-1">
                                +{stat.change.value}% {stat.change.period}
                            </p>
                        </div>
                        {stat.icon && (
                            <div className={`p-3 rounded-full bg-${stat.color}-100`}>
                                <stat.icon className={`h-6 w-6 text-${stat.color}-600`} />
                            </div>
                        )}
                    </div>
                </CardContent>
            </Card>
        ))}
    </div>
);

function OrdersManagementPage() {
    const [orders] = React.useState(mockOrders);
    const [searchQuery, setSearchQuery] = React.useState('');
    const [statusFilter, setStatusFilter] = React.useState('all');
    const [paymentFilter, setPaymentFilter] = React.useState('all');
    const [selectedOrders, setSelectedOrders] = React.useState<string[]>([]);
    const [selectedOrder, setSelectedOrder] = React.useState<any>(null);
    const [showOrderDialog, setShowOrderDialog] = React.useState(false);

    // Filter orders based on search and filters
    const filteredOrders = orders.filter(order => {
        const matchesSearch = order.id.toLowerCase().includes(searchQuery.toLowerCase()) ||
            order.customer.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            order.customer.email.toLowerCase().includes(searchQuery.toLowerCase());
        const matchesStatus = statusFilter === 'all' || order.status === statusFilter;
        const matchesPayment = paymentFilter === 'all' || order.payment_status === paymentFilter;

        return matchesSearch && matchesStatus && matchesPayment;
    });

    const handleSelectAll = () => {
        if (selectedOrders.length === filteredOrders.length) {
            setSelectedOrders([]);
        } else {
            setSelectedOrders(filteredOrders.map(order => order.id));
        }
    };

    const handleSelectOrder = (orderId: string) => {
        setSelectedOrders(prev =>
            prev.includes(orderId)
                ? prev.filter(id => id !== orderId)
                : [...prev, orderId]
        );
    };

    const handleBulkAction = (action: string) => {
        console.log(`Bulk ${action} for orders:`, selectedOrders);
        setSelectedOrders([]);
    };

    const handleOrderAction = (orderId: string, action: string) => {
        console.log(`${action} order:`, orderId);
    };

    const handleViewOrder = (order: any) => {
        setSelectedOrder(order);
        setShowOrderDialog(true);
    };

    return (
        <RouteGuard requireAuth requiredRoles={['admin', 'super admin']}>
            <MainLayout>
                <div className="container mx-auto p-6 space-y-8">
                    <div className="flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Order Management</h1>
                            <p className="text-gray-600">Manage orders, track shipments, and process payments.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm">
                                <Download className="mr-2 h-4 w-4" />
                                Export
                            </Button>
                            <Button variant="outline" size="sm">
                                <RefreshCw className="mr-2 h-4 w-4" />
                                Refresh
                            </Button>
                        </div>
                    </div>

                    {/* Stats */}
                    <QuickStats stats={orderStats} />

                    {/* Filters and Search */}
                    <Card>
                        <CardHeader>
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <CardTitle className="flex items-center gap-2">
                                    <ShoppingCart className="h-5 w-5 text-primary" />
                                    Orders ({filteredOrders.length})
                                </CardTitle>

                                <div className="flex flex-col sm:flex-row gap-4">
                                    <div className="relative">
                                        <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-gray-400" />
                                        <Input
                                            placeholder="Search orders..."
                                            value={searchQuery}
                                            onChange={(e) => setSearchQuery(e.target.value)}
                                            className="pl-10 w-full sm:w-64"
                                        />
                                    </div>

                                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                                        <SelectTrigger className="w-full sm:w-40">
                                            <SelectValue placeholder="All Status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Status</SelectItem>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="processing">Processing</SelectItem>
                                            <SelectItem value="shipped">Shipped</SelectItem>
                                            <SelectItem value="completed">Completed</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <Select value={paymentFilter} onValueChange={setPaymentFilter}>
                                        <SelectTrigger className="w-full sm:w-40">
                                            <SelectValue placeholder="All Payments" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Payments</SelectItem>
                                            <SelectItem value="paid">Paid</SelectItem>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="failed">Failed</SelectItem>
                                            <SelectItem value="refunded">Refunded</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Bulk Actions */}
                            {selectedOrders.length > 0 && (
                                <motion.div
                                    initial={{ opacity: 0, height: 0 }}
                                    animate={{ opacity: 1, height: 'auto' }}
                                    exit={{ opacity: 0, height: 0 }}
                                    className="flex items-center gap-2 p-4 bg-primary/5 border border-primary/20 rounded-lg"
                                >
                                    <span className="text-sm font-medium">
                                        {selectedOrders.length} order(s) selected
                                    </span>
                                    <div className="flex gap-2 ml-4">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('process')}
                                        >
                                            <Package className="mr-2 h-4 w-4" />
                                            Process
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('ship')}
                                        >
                                            <Truck className="mr-2 h-4 w-4" />
                                            Ship
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => handleBulkAction('export')}
                                        >
                                            <Download className="mr-2 h-4 w-4" />
                                            Export
                                        </Button>
                                    </div>
                                </motion.div>
                            )}
                        </CardHeader>

                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-12">
                                                <Checkbox
                                                    checked={selectedOrders.length === filteredOrders.length && filteredOrders.length > 0}
                                                    onCheckedChange={handleSelectAll}
                                                />
                                            </TableHead>
                                            <TableHead>Order</TableHead>
                                            <TableHead>Customer</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Payment</TableHead>
                                            <TableHead>Total</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead className="w-12"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredOrders.map((order, index) => (
                                            <motion.tr
                                                key={order.id}
                                                initial={{ opacity: 0, y: 20 }}
                                                animate={{ opacity: 1, y: 0 }}
                                                transition={{ duration: 0.3, delay: index * 0.05 }}
                                                className="hover:bg-gray-50"
                                            >
                                                <TableCell>
                                                    <Checkbox
                                                        checked={selectedOrders.includes(order.id)}
                                                        onCheckedChange={() => handleSelectOrder(order.id)}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <p className="font-medium text-gray-900">
                                                            {order.id}
                                                        </p>
                                                        <p className="text-sm text-gray-500">
                                                            {order.items_count} {order.items_count === 1 ? 'item' : 'items'}
                                                        </p>
                                                        {order.tracking_number && (
                                                            <p className="text-xs text-blue-600">
                                                                Track: {order.tracking_number}
                                                            </p>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <div className="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center">
                                                            <User className="h-4 w-4 text-gray-600" />
                                                        </div>
                                                        <div>
                                                            <p className="font-medium text-gray-900">
                                                                {order.customer.name}
                                                            </p>
                                                            <p className="text-sm text-gray-500">
                                                                {order.customer.email}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={getStatusColor(order.status)}>
                                                        {order.status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={getPaymentStatusColor(order.payment_status)}>
                                                        {order.payment_status}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="font-medium">
                                                        {formatCurrency(order.total)}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <span className="text-sm text-gray-600">
                                                        {formatDate(order.created_at)}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                            <DropdownMenuItem onClick={() => handleViewOrder(order)}>
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                View Details
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleOrderAction(order.id, 'edit')}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                Edit Order
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleOrderAction(order.id, 'invoice')}>
                                                                <FileText className="mr-2 h-4 w-4" />
                                                                View Invoice
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            {order.status === 'pending' && (
                                                                <DropdownMenuItem onClick={() => handleOrderAction(order.id, 'process')}>
                                                                    <Package className="mr-2 h-4 w-4" />
                                                                    Process Order
                                                                </DropdownMenuItem>
                                                            )}
                                                            {order.status === 'processing' && (
                                                                <DropdownMenuItem onClick={() => handleOrderAction(order.id, 'ship')}>
                                                                    <Truck className="mr-2 h-4 w-4" />
                                                                    Mark as Shipped
                                                                </DropdownMenuItem>
                                                            )}
                                                            <DropdownMenuItem onClick={() => handleOrderAction(order.id, 'contact')}>
                                                                <Mail className="mr-2 h-4 w-4" />
                                                                Contact Customer
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </motion.tr>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            {filteredOrders.length === 0 && (
                                <div className="text-center py-12">
                                    <ShoppingCart className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                    <h3 className="text-lg font-medium text-gray-900 mb-2">
                                        No Orders Found
                                    </h3>
                                    <p className="text-gray-500">
                                        {searchQuery || statusFilter !== 'all' || paymentFilter !== 'all'
                                            ? 'Try adjusting your search or filters.'
                                            : 'No orders have been placed yet.'
                                        }
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Order Details Dialog */}
                    <Dialog open={showOrderDialog} onOpenChange={setShowOrderDialog}>
                        <DialogContent className="max-w-4xl max-h-[80vh] overflow-y-auto">
                            <DialogHeader>
                                <DialogTitle>
                                    Order Details - {selectedOrder?.id}
                                </DialogTitle>
                            </DialogHeader>
                            {selectedOrder && (
                                <div className="space-y-6">
                                    <Tabs defaultValue="details" className="w-full">
                                        <TabsList className="grid w-full grid-cols-4">
                                            <TabsTrigger value="details">Details</TabsTrigger>
                                            <TabsTrigger value="customer">Customer</TabsTrigger>
                                            <TabsTrigger value="shipping">Shipping</TabsTrigger>
                                            <TabsTrigger value="history">History</TabsTrigger>
                                        </TabsList>

                                        <TabsContent value="details" className="space-y-4">
                                            <div className="grid grid-cols-2 gap-4">
                                                <div>
                                                    <h4 className="font-medium mb-2">Order Information</h4>
                                                    <div className="space-y-2 text-sm">
                                                        <p><strong>Order ID:</strong> {selectedOrder.id}</p>
                                                        <p><strong>Status:</strong>
                                                            <Badge className={cn('ml-2', getStatusColor(selectedOrder.status))}>
                                                                {selectedOrder.status}
                                                            </Badge>
                                                        </p>
                                                        <p><strong>Payment:</strong>
                                                            <Badge className={cn('ml-2', getPaymentStatusColor(selectedOrder.payment_status))}>
                                                                {selectedOrder.payment_status}
                                                            </Badge>
                                                        </p>
                                                        <p><strong>Total:</strong> {formatCurrency(selectedOrder.total)}</p>
                                                    </div>
                                                </div>
                                                <div>
                                                    <h4 className="font-medium mb-2">Products</h4>
                                                    <div className="space-y-2">
                                                        {selectedOrder.products.map((product: any, index: number) => (
                                                            <div key={index} className="flex justify-between text-sm">
                                                                <span>{product.name} × {product.quantity}</span>
                                                                <span>{formatCurrency(product.price)}</span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            </div>
                                        </TabsContent>

                                        <TabsContent value="customer" className="space-y-4">
                                            <div>
                                                <h4 className="font-medium mb-2">Customer Information</h4>
                                                <div className="space-y-2 text-sm">
                                                    <p><strong>Name:</strong> {selectedOrder.customer.name}</p>
                                                    <p><strong>Email:</strong> {selectedOrder.customer.email}</p>
                                                    <p><strong>Phone:</strong> {selectedOrder.customer.phone}</p>
                                                </div>
                                            </div>
                                        </TabsContent>

                                        <TabsContent value="shipping" className="space-y-4">
                                            <div>
                                                <h4 className="font-medium mb-2">Shipping Address</h4>
                                                <div className="text-sm">
                                                    <p>{selectedOrder.shipping_address.line1}</p>
                                                    <p>{selectedOrder.shipping_address.city}, {selectedOrder.shipping_address.postcode}</p>
                                                    <p>{selectedOrder.shipping_address.country}</p>
                                                </div>
                                                {selectedOrder.tracking_number && (
                                                    <div className="mt-4">
                                                        <p className="text-sm"><strong>Tracking:</strong> {selectedOrder.tracking_number}</p>
                                                    </div>
                                                )}
                                            </div>
                                        </TabsContent>

                                        <TabsContent value="history" className="space-y-4">
                                            <div>
                                                <h4 className="font-medium mb-2">Order History</h4>
                                                <div className="space-y-2 text-sm">
                                                    <p><strong>Created:</strong> {formatDate(selectedOrder.created_at)}</p>
                                                    <p><strong>Updated:</strong> {formatDate(selectedOrder.updated_at)}</p>
                                                    {selectedOrder.notes && (
                                                        <p><strong>Notes:</strong> {selectedOrder.notes}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </TabsContent>
                                    </Tabs>
                                </div>
                            )}
                        </DialogContent>
                    </Dialog>
                </div>
            </MainLayout>
        </RouteGuard>
    );
}

export default OrdersManagementPage;