'use client'

import * as React from 'react';
import Link from 'next/link';
import {motion} from 'framer-motion';
import {
    Palette,
    Heart,
    Mail,
    Phone,
    MapPin,
    Facebook,
    Twitter,
    Instagram,
    Youtube,
    ArrowRight,
} from 'lucide-react';
import {Button, Input} from '@/components/ui';
import {cn} from '@/lib/cn';

interface FooterProps {
    className?: string;
}

const footerLinks = {
    products: [
        {label: 'Custom Labels', href: '/products/labels'},
        {label: 'Wedding Invitations', href: '/products/invitations'},
        {label: 'Gift Tags', href: '/products/gift-tags'},
        {label: 'Stickers & Decals', href: '/products/stickers'},
        {label: 'Greeting Cards', href: '/products/greeting-cards'},
        {label: 'Packaging Inserts', href: '/products/packaging'},
    ],
    services: [
        {label: 'Custom Design', href: '/services/custom-design'},
        {label: 'Flower Stands', href: '/services/flower-stands'},
        {label: 'Bulk Orders', href: '/services/bulk-orders'},
        {label: 'Rush Printing', href: '/services/rush-printing'},
        {label: 'Design Consultation', href: '/services/consultation'},
        {label: 'Corporate Solutions', href: '/services/corporate'},
    ],
    company: [
        {label: 'About Us', href: '/about'},
        {label: 'Our Story', href: '/story'},
        {label: 'Careers', href: '/careers'},
        {label: 'Press', href: '/press'},
        {label: 'Blog', href: '/blog'},
        {label: 'Reviews', href: '/reviews'},
    ],
    support: [
        {label: 'Help Center', href: '/help'},
        {label: 'Contact Us', href: '/contact'},
        {label: 'Size Guide', href: '/size-guide'},
        {label: 'Shipping Info', href: '/shipping'},
        {label: 'Returns', href: '/returns'},
        {label: 'Track Order', href: '/track'},
    ],
    legal: [
        {label: 'Privacy Policy', href: '/privacy'},
        {label: 'Terms of Service', href: '/terms'},
        {label: 'Cookie Policy', href: '/cookies'},
        {label: 'GDPR', href: '/gdpr'},
        {label: 'Accessibility', href: '/accessibility'},
    ],
};

const socialLinks = [
    {
        name: 'Facebook',
        href: 'https://facebook.com/creativebusiness',
        icon: Facebook,
        color: 'hover:text-blue-600'
    },
    {
        name: 'Twitter',
        href: 'https://twitter.com/creativebusiness',
        icon: Twitter,
        color: 'hover:text-blue-400'
    },
    {
        name: 'Instagram',
        href: 'https://instagram.com/creativebusiness',
        icon: Instagram,
        color: 'hover:text-pink-600'
    },
    {
        name: 'YouTube',
        href: 'https://youtube.com/creativebusiness',
        icon: Youtube,
        color: 'hover:text-red-600'
    },
];

export const Footer: React.FC<FooterProps> = ({className}) => {
    const [email, setEmail] = React.useState('');
    const [isSubscribing, setIsSubscribing] = React.useState(false);

    const handleNewsletterSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!email.trim()) return;

        setIsSubscribing(true);
        try {
            // TODO: Implement newsletter subscription
            await new Promise(resolve => setTimeout(resolve, 1000)); // Simulate API call
            console.log('Newsletter subscription:', email);
            setEmail('');
            // TODO: Show success toast
        } catch (error) {
            console.error('Newsletter subscription failed:', error);
        } finally {
            setIsSubscribing(false);
        }
    };

    return (
        <footer className={cn('bg-background border-t', className)}>
            {/* Newsletter Section */}
            <div className="border-b">
                <div className="container mx-auto px-4 py-12">
                    <motion.div
                        initial={{opacity: 0, y: 20}}
                        whileInView={{opacity: 1, y: 0}}
                        transition={{duration: 0.6}}
                        viewport={{once: true}}
                        className="max-w-2xl mx-auto text-center"
                    >
                        <h3 className="text-2xl font-bold mb-4">Stay Creative with Us</h3>
                        <p className="text-muted-foreground mb-6">
                            Get design inspiration, exclusive offers, and be the first to know about new products.
                        </p>
                        <form onSubmit={handleNewsletterSubmit}
                              className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                            <Input
                                type="email"
                                placeholder="Enter your email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                required
                                className="flex-1"
                            />
                            <Button type="submit" disabled={isSubscribing}>
                                {isSubscribing ? (
                                    'Subscribing...'
                                ) : (
                                    <>
                                        Subscribe
                                        <ArrowRight className="ml-2 h-4 w-4"/>
                                    </>
                                )}
                            </Button>
                        </form>
                    </motion.div>
                </div>
            </div>

            {/* Main Footer Content */}
            <div className="container mx-auto px-4 py-16">
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-8">
                    {/* Brand */}
                    <motion.div
                        initial={{opacity: 0, y: 20}}
                        whileInView={{opacity: 1, y: 0}}
                        transition={{duration: 0.5}}
                        viewport={{once: true}}
                        className="col-span-2 md:col-span-1 lg:col-span-2"
                    >
                        <Link href="/" className="flex items-center gap-2 mb-4">
                            <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                                <Palette className="h-5 w-5 text-primary-foreground"/>
                            </div>
                            <span className="text-xl font-bold">Creative Business</span>
                        </Link>
                        <p className="text-muted-foreground mb-6 leading-relaxed">
                            Creating beautiful, professional printing solutions for life's special moments.
                            From custom labels to wedding invitations, we bring your vision to life.
                        </p>
                        <div className="flex gap-3">
                            {socialLinks.map((social, index) => (
                                <Button
                                    key={index}
                                    variant="outline"
                                    size="sm"
                                    asChild
                                    className={`${social.color} transition-colors`}
                                >
                                    <Link href={social.href} target="_blank" rel="noopener noreferrer">
                                        <span className="flex items-center">
                                            <social.icon className="h-4 w-4"/>
                                            <span className="sr-only">{social.name}</span>
                                        </span>
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    </motion.div>

                    {/* Products */}
                    <motion.div
                        initial={{opacity: 0, y: 20}}
                        whileInView={{opacity: 1, y: 0}}
                        transition={{duration: 0.5, delay: 0.1}}
                        viewport={{once: true}}
                    >
                        <h4 className="font-semibold mb-4">Products</h4>
                        <ul className="space-y-3">
                            {footerLinks.products.map((link, index) => (
                                <li key={index}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </motion.div>

                    {/* Services */}
                    <motion.div
                        initial={{opacity: 0, y: 20}}
                        whileInView={{opacity: 1, y: 0}}
                        transition={{duration: 0.5, delay: 0.2}}
                        viewport={{once: true}}
                    >
                        <h4 className="font-semibold mb-4">Services</h4>
                        <ul className="space-y-3">
                            {footerLinks.services.map((link, index) => (
                                <li key={index}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </motion.div>

                    {/* Company */}
                    <motion.div
                        initial={{opacity: 0, y: 20}}
                        whileInView={{opacity: 1, y: 0}}
                        transition={{duration: 0.5, delay: 0.3}}
                        viewport={{once: true}}
                    >
                        <h4 className="font-semibold mb-4">Company</h4>
                        <ul className="space-y-3">
                            {footerLinks.company.map((link, index) => (
                                <li key={index}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </motion.div>

                    {/* Support */}
                    <motion.div
                        initial={{opacity: 0, y: 20}}
                        whileInView={{opacity: 1, y: 0}}
                        transition={{duration: 0.5, delay: 0.4}}
                        viewport={{once: true}}
                    >
                        <h4 className="font-semibold mb-4">Support</h4>
                        <ul className="space-y-3">
                            {footerLinks.support.map((link, index) => (
                                <li key={index}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </motion.div>

                    {/* Legal */}
                    <motion.div
                        initial={{opacity: 0, y: 20}}
                        whileInView={{opacity: 1, y: 0}}
                        transition={{duration: 0.5, delay: 0.5}}
                        viewport={{once: true}}
                    >
                        <h4 className="font-semibold mb-4">Legal</h4>
                        <ul className="space-y-3">
                            {footerLinks.legal.map((link, index) => (
                                <li key={index}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </motion.div>
                </div>
            </div>

            {/* Bottom Bar */}
            <div className="border-t bg-muted/30">
                <div className="container mx-auto px-4 py-6">
                    <motion.div
                        initial={{opacity: 0}}
                        whileInView={{opacity: 1}}
                        transition={{duration: 0.5}}
                        viewport={{once: true}}
                        className="flex flex-col md:flex-row justify-between items-center gap-4"
                    >
                        <div className="flex items-center gap-6 text-sm text-muted-foreground">
                            <p>© 2025 Creative Business. All rights reserved.</p>
                            <div className="flex items-center gap-2">
                                <Heart className="h-4 w-4 text-red-500"/>
                                <span>Made in London</span>
                            </div>
                        </div>

                        <div className="flex items-center gap-6">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Mail className="h-4 w-4"/>
                                <Link
                                    href="mailto:hello@creativebusiness.com"
                                    className="hover:text-foreground transition-colors"
                                >
                                    hello@creativebusiness.com
                                </Link>
                            </div>
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Phone className="h-4 w-4"/>
                                <Link
                                    href="tel:+441234567890"
                                    className="hover:text-foreground transition-colors"
                                >
                                    +44 123 456 7890
                                </Link>
                            </div>
                        </div>
                    </motion.div>
                </div>
            </div>
        </footer>
    );
};

export default Footer;