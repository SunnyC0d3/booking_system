import * as React from 'react';
import Link from 'next/link';
import { motion } from 'framer-motion';
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
import { Button, Input } from '@/components/ui';
import { cn } from '@/lib/cn';

interface FooterProps {
    className?: string;
}

const footerLinks = {
    products: [
        { label: 'Custom Labels', href: '/products/labels' },
        { label: 'Wedding Invitations', href: '/products/invitations' },
        { label: 'Gift Tags', href: '/products/gift-tags' },
        { label: 'Stickers & Decals', href: '/products/stickers' },
        { label: 'Greeting Cards', href: '/products/greeting-cards' },
        { label: 'Packaging Inserts', href: '/products/packaging' },
    ],
    services: [
        { label: 'Custom Design', href: '/services/custom-design' },
        { label: 'Flower Stands', href: '/services/flower-stands' },
        { label: 'Bulk Orders', href: '/services/bulk-orders' },
        { label: 'Rush Printing', href: '/services/rush-printing' },
        { label: 'Design Consultation', href: '/services/consultation' },
        { label: 'Corporate Solutions', href: '/services/corporate' },
    ],
    company: [
        { label: 'About Us', href: '/about' },
        { label: 'Our Story', href: '/story' },
        { label: 'Careers', href: '/careers' },
        { label: 'Press', href: '/press' },
        { label: 'Blog', href: '/blog' },
        { label: 'Reviews', href: '/reviews' },
    ],
    support: [
        { label: 'Help Center', href: '/help' },
        { label: 'Contact Us', href: '/contact' },
        { label: 'Size Guide', href: '/size-guide' },
        { label: 'Shipping Info', href: '/shipping' },
        { label: 'Returns', href: '/returns' },
        { label: 'Track Order', href: '/track' },
    ],
    legal: [
        { label: 'Privacy Policy', href: '/privacy' },
        { label: 'Terms of Service', href: '/terms' },
        { label: 'Cookie Policy', href: '/cookies' },
        { label: 'GDPR', href: '/gdpr' },
        { label: 'Accessibility', href: '/accessibility' },
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

export const Footer: React.FC<FooterProps> = ({ className }) => {
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
            {/* Main Footer Content */}
            <div className="container mx-auto px-4 py-12">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-8">
                    {/* Brand & Newsletter */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Logo */}
                        <Link href="/" className="flex items-center gap-3 group">
                            <div className="relative">
                                <div className="w-12 h-12 bg-primary rounded-xl flex items-center justify-center group-hover:shadow-glow transition-all duration-300">
                                    <Palette className="h-6 w-6 text-primary-foreground" />
                                </div>
                                <div className="absolute -top-1 -right-1 w-5 h-5 bg-cream-400 rounded-full flex items-center justify-center">
                                    <Heart className="h-3 w-3 text-white" />
                                </div>
                            </div>
                            <div>
                                <h2 className="font-bold text-xl text-foreground group-hover:text-primary transition-colors">
                                    Creative Business
                                </h2>
                                <p className="text-sm text-muted-foreground -mt-1">
                                    Professional Printing Services
                                </p>
                            </div>
                        </Link>

                        <p className="text-muted-foreground text-sm leading-relaxed">
                            Transform your creative vision into reality with our premium labels,
                            invitations, stickers, and custom printing services. Quality and
                            creativity in every project.
                        </p>

                        {/* Newsletter Signup */}
                        <div className="space-y-3">
                            <h3 className="font-semibold text-foreground">Stay Updated</h3>
                            <p className="text-sm text-muted-foreground">
                                Get the latest designs, offers, and creative inspiration.
                            </p>
                            <form onSubmit={handleNewsletterSubmit} className="flex gap-2">
                                <Input
                                    type="email"
                                    placeholder="Enter your email"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    className="flex-1"
                                    required
                                />
                                <Button
                                    type="submit"
                                    loading={isSubscribing}
                                    className="shrink-0"
                                >
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </form>
                        </div>
                    </div>

                    {/* Products */}
                    <div className="space-y-4">
                        <h3 className="font-semibold text-foreground">Products</h3>
                        <ul className="space-y-2">
                            {footerLinks.products.map((link) => (
                                <li key={link.href}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Services */}
                    <div className="space-y-4">
                        <h3 className="font-semibold text-foreground">Services</h3>
                        <ul className="space-y-2">
                            {footerLinks.services.map((link) => (
                                <li key={link.href}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Company */}
                    <div className="space-y-4">
                        <h3 className="font-semibold text-foreground">Company</h3>
                        <ul className="space-y-2">
                            {footerLinks.company.map((link) => (
                                <li key={link.href}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Support */}
                    <div className="space-y-4">
                        <h3 className="font-semibold text-foreground">Support</h3>
                        <ul className="space-y-2">
                            {footerLinks.support.map((link) => (
                                <li key={link.href}>
                                    <Link
                                        href={link.href}
                                        className="text-sm text-muted-foreground hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                {/* Contact Info & Social */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-12 pt-8 border-t">
                    {/* Contact Information */}
                    <div className="space-y-4">
                        <h3 className="font-semibold text-foreground">Get in Touch</h3>
                        <div className="space-y-3">
                            <div className="flex items-center gap-3 text-sm">
                                <Mail className="h-4 w-4 text-primary" />
                                <span className="text-muted-foreground">
                  hello@creativebusiness.com
                </span>
                            </div>
                            <div className="flex items-center gap-3 text-sm">
                                <Phone className="h-4 w-4 text-primary" />
                                <span className="text-muted-foreground">
                  +1 (555) 123-4567
                </span>
                            </div>
                            <div className="flex items-center gap-3 text-sm">
                                <MapPin className="h-4 w-4 text-primary" />
                                <span className="text-muted-foreground">
                  123 Creative Street, Design City, DC 12345
                </span>
                            </div>
                        </div>
                    </div>

                    {/* Social Links */}
                    <div className="space-y-4">
                        <h3 className="font-semibold text-foreground">Follow Us</h3>
                        <div className="flex gap-4">
                            {socialLinks.map((social) => {
                                const Icon = social.icon;
                                return (
                                    <motion.a
                                        key={social.name}
                                        href={social.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        whileHover={{ scale: 1.1 }}
                                        whileTap={{ scale: 0.95 }}
                                        className={cn(
                                            'w-10 h-10 rounded-lg bg-muted flex items-center justify-center text-muted-foreground transition-colors',
                                            social.color
                                        )}
                                    >
                                        <Icon className="h-5 w-5" />
                                        <span className="sr-only">{social.name}</span>
                                    </motion.a>
                                );
                            })}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Join our community for daily inspiration and exclusive offers!
                        </p>
                    </div>
                </div>
            </div>

            {/* Bottom Bar */}
            <div className="border-t bg-muted/30">
                <div className="container mx-auto px-4 py-6">
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div className="flex flex-wrap items-center gap-6 text-sm text-muted-foreground">
                            {footerLinks.legal.map((link, index) => (
                                <React.Fragment key={link.href}>
                                    <Link
                                        href={link.href}
                                        className="hover:text-foreground transition-colors"
                                    >
                                        {link.label}
                                    </Link>
                                    {index < footerLinks.legal.length - 1 && (
                                        <span className="hidden sm:inline">•</span>
                                    )}
                                </React.Fragment>
                            ))}
                        </div>

                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <span>© 2024 Creative Business. Made with</span>
                            <Heart className="h-4 w-4 text-primary" />
                            <span>for creators.</span>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    );
};

export default Footer;