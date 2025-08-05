import * as React from 'react';
import { Metadata } from 'next';
import Link from 'next/link';
import { motion } from 'framer-motion';
import {
    Tag,
    Heart,
    Sticker,
    Mail,
    Package,
    Flower,
    Zap,
    Users,
    Building,
    ArrowRight,
    Check,
    Phone,
    MessageCircle,
} from 'lucide-react';
import { Button, Card, CardContent, CardHeader, CardTitle, Badge } from '@/components/ui';
import { MainLayout } from '@/components/layout';

export const metadata: Metadata = {
    title: 'Our Services | Creative Business',
    description: 'Professional printing and design services including custom labels, invitations, gift tags, stickers, greeting cards, and flower stands.',
};

// Main services data
const mainServices = [
    {
        icon: Tag,
        title: 'Custom Labels',
        description: 'Professional labels for products, events, and branding needs.',
        features: [
            'Product labels & branding',
            'Address & shipping labels',
            'Event & party labels',
            'Waterproof & durable options',
            'Various shapes & sizes',
            'Premium materials',
        ],
        startingPrice: '£15',
        popular: true,
        image: '/images/services/custom-labels.jpg',
        href: '/services/custom-labels',
    },
    {
        icon: Heart,
        title: 'Wedding Invitations',
        description: 'Beautiful wedding and event invitations that set the perfect tone.',
        features: [
            'Custom wedding suites',
            'Save the dates',
            'RSVP cards',
            'Thank you cards',
            'Menu cards',
            'Premium paper options',
        ],
        startingPrice: '£45',
        popular: true,
        image: '/images/services/wedding-invitations.jpg',
        href: '/services/wedding-invitations',
    },
    {
        icon: Package,
        title: 'Gift Tags',
        description: 'Perfect finishing touches for any gift or special occasion.',
        features: [
            'Custom gift tags',
            'Holiday tags',
            'Birthday tags',
            'Business gift tags',
            'Bulk options available',
            'String & attachment included',
        ],
        startingPrice: '£8',
        popular: false,
        image: '/images/services/gift-tags.jpg',
        href: '/services/gift-tags',
    },
    {
        icon: Sticker,
        title: 'Stickers & Decals',
        description: 'High-quality stickers for branding, decoration, and promotion.',
        features: [
            'Custom shapes & sizes',
            'Vinyl & paper options',
            'Waterproof materials',
            'Die-cut stickers',
            'Bulk discounts',
            'Indoor & outdoor use',
        ],
        startingPrice: '£12',
        popular: false,
        image: '/images/services/stickers.jpg',
        href: '/services/stickers',
    },
    {
        icon: Mail,
        title: 'Greeting Cards',
        description: 'Personalized greeting cards for every occasion and celebration.',
        features: [
            'Birthday cards',
            'Holiday cards',
            'Thank you cards',
            'Business cards',
            'Custom messages',
            'Premium cardstock',
        ],
        startingPrice: '£18',
        popular: false,
        image: '/images/services/greeting-cards.jpg',
        href: '/services/greeting-cards',
    },
    {
        icon: Package,
        title: 'Packaging Inserts',
        description: 'Professional packaging materials to enhance your brand presentation.',
        features: [
            'Custom inserts',
            'Brand messaging',
            'Thank you notes',
            'Care instructions',
            'Product information',
            'Various sizes',
        ],
        startingPrice: '£25',
        popular: false,
        image: '/images/services/packaging.jpg',
        href: '/services/packaging',
    },
];

// Specialty services
const specialtyServices = [
    {
        icon: Flower,
        title: 'Flower Stands',
        description: 'Custom-built flower stands for events, shops, and displays.',
        features: [
            'Custom designs',
            'Various materials',
            'Event installations',
            'Shop displays',
            'Delivery & setup',
        ],
        href: '/services/flower-stands',
    },
    {
        icon: Zap,
        title: 'Rush Orders',
        description: 'Fast turnaround service for urgent printing needs.',
        features: [
            '24-48 hour delivery',
            'Same-day available',
            'Quality guaranteed',
            'Priority handling',
            'Express shipping',
        ],
        href: '/services/rush-orders',
    },
    {
        icon: Users,
        title: 'Design Consultation',
        description: 'Professional design advice and creative guidance.',
        features: [
            'One-on-one consultation',
            'Design recommendations',
            'Brand guidance',
            'Material selection',
            'Project planning',
        ],
        href: '/services/consultation',
    },
    {
        icon: Building,
        title: 'Corporate Solutions',
        description: 'Comprehensive printing services for businesses and organizations.',
        features: [
            'Bulk pricing',
            'Account management',
            'Custom workflows',
            'Brand compliance',
            'Regular orders',
        ],
        href: '/services/corporate',
    },
];

// Process steps
const processSteps = [
    {
        step: '01',
        title: 'Consultation',
        description: 'We discuss your vision, requirements, and timeline to understand your needs.',
    },
    {
        step: '02',
        title: 'Design',
        description: 'Our team creates custom designs based on your specifications and brand.',
    },
    {
        step: '03',
        title: 'Review',
        description: 'You review and approve the design, with revisions included.',
    },
    {
        step: '04',
        title: 'Production',
        description: 'We carefully print and craft your items using premium materials.',
    },
    {
        step: '05',
        title: 'Delivery',
        description: 'Your finished products are quality-checked and delivered on time.',
    },
];

// Pricing packages
const packages = [
    {
        name: 'Starter',
        price: '£49',
        description: 'Perfect for small projects and personal use',
        features: [
            'Up to 50 pieces',
            '1 design revision',
            'Standard materials',
            '5-7 day turnaround',
            'Digital proof included',
        ],
        popular: false,
    },
    {
        name: 'Professional',
        price: '£149',
        description: 'Ideal for businesses and larger projects',
        features: [
            'Up to 250 pieces',
            '3 design revisions',
            'Premium materials',
            '3-5 day turnaround',
            'Digital & physical proof',
            'Dedicated support',
        ],
        popular: true,
    },
    {
        name: 'Enterprise',
        price: '£349',
        description: 'Comprehensive solution for large organizations',
        features: [
            'Up to 1000 pieces',
            'Unlimited revisions',
            'Premium materials',
            '1-3 day turnaround',
            'Account manager',
            'Bulk discounts',
            'Priority support',
        ],
        popular: false,
    },
];

export default function ServicesPage() {
    return (
        <MainLayout>
            {/* Hero Section */}
            <section className="relative py-20 lg:py-32 bg-gradient-creative">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        className="text-center max-w-4xl mx-auto"
                    >
                        <h1 className="text-4xl lg:text-6xl font-bold text-foreground mb-6">
                            Creative Services That{' '}
                            <span className="text-gradient">Bring Ideas to Life</span>
                        </h1>
                        <p className="text-xl text-muted-foreground mb-8 leading-relaxed">
                            From custom labels to wedding invitations, we offer comprehensive
                            printing and design services that combine creativity with quality craftsmanship.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <Button size="lg">
                                <Link href="/contact">
                                    <span className="flex items-center">
                                        Start Your Project
                                        <ArrowRight className="ml-2 h-4 w-4" />
                                    </span>
                                </Link>
                            </Button>
                            <Button variant="outline" size="lg">
                                <Link href="#services">
                                    <span className="flex items-center">
                                        View Services
                                    </span>
                                </Link>
                            </Button>
                        </div>
                    </motion.div>
                </div>
            </section>

            {/* Main Services Grid */}
            <section id="services" className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-16"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-4">Our Core Services</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            Professional printing solutions for every need, backed by years of
                            experience and commitment to quality.
                        </p>
                    </motion.div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                        {mainServices.map((service, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className="h-full hover:shadow-lg transition-all duration-300 group">
                                    <div className="relative">
                                        <img
                                            src={service.image}
                                            alt={service.title}
                                            className="w-full h-48 object-cover rounded-t-lg"
                                            onError={(e) => {
                                                e.currentTarget.src = `/images/services/placeholder-${index + 1}.jpg`;
                                            }}
                                        />
                                        {service.popular && (
                                            <Badge className="absolute top-4 left-4 bg-primary text-primary-foreground">
                                                Popular
                                            </Badge>
                                        )}
                                        <div className="absolute top-4 right-4 bg-white rounded-lg px-3 py-1 shadow-md">
                                            <span className="text-sm font-bold text-primary">
                                                From {service.startingPrice}
                                            </span>
                                        </div>
                                    </div>

                                    <CardHeader>
                                        <div className="flex items-center gap-3 mb-2">
                                            <div className="w-10 h-10 bg-primary/10 rounded-lg flex items-center justify-center">
                                                <service.icon className="h-5 w-5 text-primary" />
                                            </div>
                                            <CardTitle className="text-xl">{service.title}</CardTitle>
                                        </div>
                                        <p className="text-muted-foreground">{service.description}</p>
                                    </CardHeader>

                                    <CardContent className="space-y-4">
                                        <div className="space-y-2">
                                            {service.features.map((feature, idx) => (
                                                <div key={idx} className="flex items-center gap-2">
                                                    <Check className="h-4 w-4 text-primary flex-shrink-0" />
                                                    <span className="text-sm text-muted-foreground">{feature}</span>
                                                </div>
                                            ))}
                                        </div>

                                        <Button className="w-full group-hover:bg-primary/90 transition-colors">
                                            <Link href={service.href}>
                                                <span className="flex items-center">
                                                    Learn More
                                                    <ArrowRight className="ml-2 h-4 w-4" />
                                                </span>
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Specialty Services */}
            <section className="py-20 bg-muted/20">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-16"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-4">Specialty Services</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            Additional services to support your creative projects and business needs.
                        </p>
                    </motion.div>

                    <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                        {specialtyServices.map((service, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className="h-full hover:shadow-lg transition-shadow text-center">
                                    <CardHeader>
                                        <div className="mx-auto w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mb-4">
                                            <service.icon className="h-6 w-6 text-primary" />
                                        </div>
                                        <CardTitle className="text-lg">{service.title}</CardTitle>
                                        <p className="text-muted-foreground text-sm">{service.description}</p>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2 mb-4">
                                            {service.features.map((feature, idx) => (
                                                <div key={idx} className="flex items-center gap-2 justify-center">
                                                    <Check className="h-3 w-3 text-primary flex-shrink-0" />
                                                    <span className="text-xs text-muted-foreground">{feature}</span>
                                                </div>
                                            ))}
                                        </div>
                                        <Button variant="outline" size="sm" className="w-full">
                                            <Link href={service.href}>Learn More</Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Process Section */}
            <section className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-16"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-4">Our Process</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            Simple, streamlined steps to bring your creative vision to life
                            with professional results.
                        </p>
                    </motion.div>

                    <div className="relative">
                        {/* Process Timeline */}
                        <div className="hidden lg:block absolute left-1/2 transform -translate-x-1/2 h-full w-px bg-primary/20"></div>

                        <div className="space-y-12 lg:space-y-16">
                            {processSteps.map((step, index) => (
                                <motion.div
                                    key={index}
                                    initial={{ opacity: 0, x: index % 2 === 0 ? -20 : 20 }}
                                    whileInView={{ opacity: 1, x: 0 }}
                                    transition={{ duration: 0.6, delay: index * 0.1 }}
                                    viewport={{ once: true }}
                                    className={`grid lg:grid-cols-2 gap-8 items-center ${
                                        index % 2 === 1 ? 'lg:flex-row-reverse' : ''
                                    }`}
                                >
                                    <div className={`${index % 2 === 1 ? 'lg:order-2' : ''}`}>
                                        <Card className="hover:shadow-lg transition-shadow">
                                            <CardContent className="p-6">
                                                <div className="flex items-center gap-4 mb-4">
                                                    <div className="w-12 h-12 bg-primary rounded-full flex items-center justify-center text-white font-bold">
                                                        {step.step}
                                                    </div>
                                                    <h3 className="text-xl font-bold">{step.title}</h3>
                                                </div>
                                                <p className="text-muted-foreground">{step.description}</p>
                                            </CardContent>
                                        </Card>
                                    </div>
                                    <div className={`${index % 2 === 1 ? 'lg:order-1' : ''} lg:flex lg:justify-center`}>
                                        <div className="relative">
                                            <div className="hidden lg:block absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 w-4 h-4 bg-primary rounded-full border-4 border-white shadow-lg"></div>
                                        </div>
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* Pricing Packages */}
            <section className="py-20 bg-muted/20">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="text-center mb-16"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-4">Service Packages</h2>
                        <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                            Choose the package that fits your needs and budget. All packages
                            include our quality guarantee.
                        </p>
                    </motion.div>

                    <div className="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                        {packages.map((pkg, index) => (
                            <motion.div
                                key={index}
                                initial={{ opacity: 0, y: 20 }}
                                whileInView={{ opacity: 1, y: 0 }}
                                transition={{ duration: 0.5, delay: index * 0.1 }}
                                viewport={{ once: true }}
                            >
                                <Card className={`h-full text-center relative ${
                                    pkg.popular
                                        ? 'border-primary shadow-lg scale-105'
                                        : 'hover:shadow-lg'
                                } transition-all duration-300`}>
                                    {pkg.popular && (
                                        <Badge className="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-primary text-primary-foreground">
                                            Most Popular
                                        </Badge>
                                    )}

                                    <CardHeader className="pb-4">
                                        <CardTitle className="text-2xl mb-2">{pkg.name}</CardTitle>
                                        <div className="text-4xl font-bold text-primary mb-2">
                                            {pkg.price}
                                        </div>
                                        <p className="text-muted-foreground text-sm">{pkg.description}</p>
                                    </CardHeader>

                                    <CardContent className="space-y-4">
                                        <div className="space-y-3">
                                            {pkg.features.map((feature, idx) => (
                                                <div key={idx} className="flex items-center gap-2">
                                                    <Check className="h-4 w-4 text-primary flex-shrink-0" />
                                                    <span className="text-sm text-muted-foreground">{feature}</span>
                                                </div>
                                            ))}
                                        </div>

                                        <Button
                                            className={`w-full ${pkg.popular ? 'bg-primary hover:bg-primary/90' : ''}`}
                                            variant={pkg.popular ? 'default' : 'outline'}
                                           
                                        >
                                            <Link href="/contact">
                                                Get Started
                                                <ArrowRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            </motion.div>
                        ))}
                    </div>
                </div>
            </section>

            {/* CTA Section */}
            <section className="py-20 bg-gradient-creative">
                <div className="container mx-auto px-4 text-center">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        viewport={{ once: true }}
                        className="max-w-2xl mx-auto"
                    >
                        <h2 className="text-3xl lg:text-4xl font-bold mb-6">
                            Ready to Start Your Project?
                        </h2>
                        <p className="text-xl text-muted-foreground mb-8">
                            Let's discuss your creative needs and bring your vision to life
                            with our professional services.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center">
                            <Button size="lg">
                                <Link href="/contact">
                                    <MessageCircle className="mr-2 h-4 w-4" />
                                    Start Discussion
                                </Link>
                            </Button>
                            <Button variant="outline" size="lg">
                                <Link href="tel:+1555123456">
                                    <Phone className="mr-2 h-4 w-4" />
                                    Call for Quote
                                </Link>
                            </Button>
                        </div>
                    </motion.div>
                </div>
            </section>
        </MainLayout>
    );
}