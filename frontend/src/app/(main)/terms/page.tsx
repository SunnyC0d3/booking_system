import * as React from 'react';
import { Metadata } from 'next';
import { motion } from 'framer-motion';
import {
    FileText,
    Scale,
    AlertCircle,
    Clock,
    Mail,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui';
import { MainLayout } from '@/components/layout';

export const metadata: Metadata = {
    title: 'Terms of Service | Creative Business',
    description: 'Terms and conditions for using Creative Business services and website.',
};

const termsData = {
    lastUpdated: 'January 15, 2025',
    effectiveDate: 'January 15, 2025',
    sections: [
        {
            title: 'Acceptance of Terms',
            content: [
                'By accessing and using the Creative Business website and services, you accept and agree to be bound by the terms and provision of this agreement.',
                'If you do not agree with any of these terms, you are prohibited from using or accessing this site.',
                'The materials contained in this website are protected by applicable copyright and trademark law.',
            ],
        },
        {
            title: 'Services Description',
            content: [
                'Creative Business provides custom printing and design services including but not limited to: custom labels, wedding invitations, gift tags, stickers, greeting cards, packaging inserts, and flower stand creation.',
                'All services are subject to availability and acceptance by Creative Business.',
                'We reserve the right to refuse service to anyone for any reason at any time.',
                'Service specifications, pricing, and delivery times are subject to change without notice.',
            ],
        },
        {
            title: 'Orders and Payment',
            content: [
                'All orders are subject to acceptance and availability.',
                'Prices are quoted in British Pounds (GBP) and are subject to change without notice.',
                'Payment is due at the time of order unless otherwise agreed in writing.',
                'We accept major credit cards, PayPal, and bank transfers.',
                'All payments must be received before production begins unless credit terms have been arranged.',
                'Rush orders may incur additional charges as specified at the time of order.',
            ],
        },
        {
            title: 'Design and Artwork',
            content: [
                'Customers are responsible for providing artwork that is print-ready or clearly specifying design requirements.',
                'Creative Business will make reasonable efforts to match colors, but cannot guarantee exact color reproduction due to variations in printing processes and materials.',
                'Any design work performed by Creative Business remains the property of Creative Business unless otherwise agreed in writing.',
                'Customers grant Creative Business the right to use completed work for promotional purposes unless specifically requested otherwise.',
                'Creative Business is not responsible for copyright infringement in customer-provided artwork.',
            ],
        },
        {
            title: 'Production and Delivery',
            content: [
                'Production times are estimates and may vary based on workload, complexity, and material availability.',
                'Delivery dates are estimates and Creative Business is not liable for delays beyond our reasonable control.',
                'Risk of loss and title for products pass to the customer upon delivery to the shipping carrier.',
                'Customers are responsible for inspecting deliveries and reporting any issues within 48 hours of receipt.',
                'Creative Business will make reasonable efforts to correct any errors in production at no additional charge.',
            ],
        },
        {
            title: 'Quality and Returns',
            content: [
                'Creative Business guarantees the quality of materials and workmanship for a period of 30 days from delivery.',
                'Returns are only accepted for defective products or errors in production.',
                'Custom-designed items cannot be returned unless there is a production error.',
                'All returns must be authorized in advance and returned in original condition.',
                'Refunds will be processed within 14 business days of receiving returned items.',
            ],
        },
        {
            title: 'Intellectual Property',
            content: [
                'All content on this website, including text, graphics, logos, and images, is the property of Creative Business and protected by copyright laws.',
                'Customers may not reproduce, distribute, or create derivative works from our website content without written permission.',
                'Any feedback, suggestions, or ideas provided to Creative Business may be used without compensation or attribution.',
                'Customers retain ownership rights to their original artwork and designs submitted for printing.',
            ],
        },
        {
            title: 'Limitation of Liability',
            content: [
                'Creative Business\'s liability is limited to the cost of the products or services provided.',
                'We are not liable for any indirect, incidental, special, or consequential damages.',
                'Our total liability shall not exceed the amount paid by the customer for the specific order in question.',
                'Creative Business is not responsible for delays or failures caused by events beyond our reasonable control.',
                'Customers are responsible for obtaining necessary permissions for any copyrighted or trademarked materials.',
            ],
        },
        {
            title: 'Privacy and Data Protection',
            content: [
                'Creative Business is committed to protecting customer privacy and personal information.',
                'We collect and use personal information only as described in our Privacy Policy.',
                'Customer information will not be shared with third parties except as necessary to fulfill orders.',
                'We implement appropriate security measures to protect against unauthorized access to personal information.',
                'Customers have the right to request access to, correction of, or deletion of their personal information.',
            ],
        },
        {
            title: 'Website Use',
            content: [
                'This website is provided on an "as is" basis without warranties of any kind.',
                'Creative Business does not warrant that the website will be uninterrupted or error-free.',
                'Users are prohibited from using the website for any unlawful purpose or in any way that could damage the website or impair others\' use.',
                'We reserve the right to terminate or restrict access to the website at any time without notice.',
                'Users are responsible for maintaining the confidentiality of their account information.',
            ],
        },
        {
            title: 'Dispute Resolution',
            content: [
                'Any disputes arising from these terms or the use of our services shall be governed by the laws of England and Wales.',
                'Disputes will be resolved through good faith negotiations whenever possible.',
                'If negotiations fail, disputes may be submitted to binding arbitration or resolved through the courts of England and Wales.',
                'The prevailing party in any dispute may be entitled to reasonable attorney fees and costs.',
            ],
        },
        {
            title: 'Changes to Terms',
            content: [
                'Creative Business reserves the right to modify these terms at any time without prior notice.',
                'Changes will be effective immediately upon posting to the website.',
                'Continued use of our services after changes constitutes acceptance of the modified terms.',
                'Customers are encouraged to review these terms periodically for updates.',
                'For significant changes, we will make reasonable efforts to notify customers via email or website notice.',
            ],
        },
    ],
};

export default function TermsPage() {
    return (
        <MainLayout>
            {/* Hero Section */}
            <section className="relative py-20 lg:py-32 bg-gradient-creative">
                <div className="container mx-auto px-4">
                    <motion.div
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.6 }}
                        className="text-center max-w-3xl mx-auto"
                    >
                        <div className="flex justify-center mb-6">
                            <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center">
                                <Scale className="h-8 w-8 text-primary" />
                            </div>
                        </div>
                        <h1 className="text-4xl lg:text-6xl font-bold text-foreground mb-6">
                            Terms of Service
                        </h1>
                        <p className="text-xl text-muted-foreground mb-8 leading-relaxed">
                            Please read these terms and conditions carefully before using our services.
                            These terms govern your use of Creative Business services and website.
                        </p>
                        <div className="flex flex-col sm:flex-row gap-4 justify-center text-sm text-muted-foreground">
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4" />
                                Last Updated: {termsData.lastUpdated}
                            </div>
                            <div className="flex items-center gap-2">
                                <FileText className="h-4 w-4" />
                                Effective Date: {termsData.effectiveDate}
                            </div>
                        </div>
                    </motion.div>
                </div>
            </section>

            {/* Terms Content */}
            <section className="py-20 bg-background">
                <div className="container mx-auto px-4">
                    <div className="max-w-4xl mx-auto">
                        {/* Important Notice */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mb-12"
                        >
                            <Card className="border-primary/20 bg-primary/5">
                                <CardContent className="p-6">
                                    <div className="flex items-start gap-3">
                                        <AlertCircle className="h-6 w-6 text-primary flex-shrink-0 mt-0.5" />
                                        <div>
                                            <h3 className="font-semibold text-foreground mb-2">Important Notice</h3>
                                            <p className="text-muted-foreground">
                                                By using Creative Business services, you agree to be bound by these terms.
                                                Please read them carefully. If you have any questions about these terms,
                                                please contact us before placing an order.
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Terms Sections */}
                        <div className="space-y-8">
                            {termsData.sections.map((section, index) => (
                                <motion.div
                                    key={index}
                                    initial={{ opacity: 0, y: 20 }}
                                    whileInView={{ opacity: 1, y: 0 }}
                                    transition={{ duration: 0.5, delay: index * 0.1 }}
                                    viewport={{ once: true }}
                                >
                                    <Card>
                                        <CardHeader>
                                            <CardTitle className="text-xl">
                                                {index + 1}. {section.title}
                                            </CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-4">
                                                {section.content.map((paragraph, pIndex) => (
                                                    <p key={pIndex} className="text-muted-foreground leading-relaxed">
                                                        {paragraph}
                                                    </p>
                                                ))}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </motion.div>
                            ))}
                        </div>

                        {/* Contact Information */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mt-12"
                        >
                            <Card className="border-primary/20 bg-primary/5">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Mail className="h-5 w-5 text-primary" />
                                        Questions About These Terms?
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-muted-foreground mb-4">
                                        If you have any questions about these Terms of Service, please don't hesitate to contact us:
                                    </p>
                                    <div className="space-y-2 text-sm">
                                        <p><strong>Email:</strong> legal@creativebusiness.com</p>
                                        <p><strong>Phone:</strong> +44 20 7123 4567</p>
                                        <p><strong>Address:</strong> 123 Creative Street, London, UK EC1A 1AA</p>
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Quick Links */}
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            whileInView={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.6 }}
                            viewport={{ once: true }}
                            className="mt-8 text-center"
                        >
                            <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                <a
                                    href="/privacy"
                                    className="text-primary hover:text-primary/80 transition-colors font-medium"
                                >
                                    Privacy Policy
                                </a>
                                <span className="hidden sm:inline text-muted-foreground">|</span>
                                <a
                                    href="/contact"
                                    className="text-primary hover:text-primary/80 transition-colors font-medium"
                                >
                                    Contact Us
                                </a>
                                <span className="hidden sm:inline text-muted-foreground">|</span>
                                <a
                                    href="/help"
                                    className="text-primary hover:text-primary/80 transition-colors font-medium"
                                >
                                    Help Center
                                </a>
                            </div>
                        </motion.div>
                    </div>
                </div>
            </section>
        </MainLayout>
    );
}