'use client'

import * as React from 'react';
import { motion } from 'framer-motion';
import {
  ArrowRight,
  Star,
  Shield,
  Truck,
  Palette,
  Heart,
  Sparkles,
} from 'lucide-react';
import { MainLayout } from '@/components/layout';
import { Button, Card, CardContent } from '@/components/ui';

const productCategories = [
  {
    title: 'Custom Labels',
    description: 'Professional labels for products, events, and personal use',
    icon: '🏷️',
    href: '/products/labels',
    color: 'from-pink-500 to-rose-500',
  },
  {
    title: 'Wedding Invitations',
    description: 'Elegant invitations for your special day',
    icon: '💌',
    href: '/products/invitations',
    color: 'from-purple-500 to-pink-500',
  },
  {
    title: 'Gift Tags',
    description: 'Perfect finishing touches for any gift',
    icon: '🎁',
    href: '/products/gift-tags',
    color: 'from-emerald-500 to-teal-500',
  },
  {
    title: 'Stickers & Decals',
    description: 'Custom stickers for branding and decoration',
    icon: '✨',
    href: '/products/stickers',
    color: 'from-blue-500 to-cyan-500',
  },
  {
    title: 'Greeting Cards',
    description: 'Personalized cards for every occasion',
    icon: '💝',
    href: '/products/greeting-cards',
    color: 'from-orange-500 to-red-500',
  },
  {
    title: 'Packaging',
    description: 'Professional packaging inserts and materials',
    icon: '📦',
    href: '/products/packaging',
    color: 'from-indigo-500 to-purple-500',
  },
];

const features = [
  {
    icon: Shield,
    title: 'Quality Guaranteed',
    description: 'Premium materials and professional printing with satisfaction guarantee',
  },
  {
    icon: Truck,
    title: 'Fast Shipping',
    description: 'Quick turnaround times with reliable delivery options',
  },
  {
    icon: Palette,
    title: 'Custom Design',
    description: 'Personalized designs tailored to your vision and brand',
  },
  {
    icon: Heart,
    title: 'Made with Love',
    description: 'Crafted with attention to detail and passion for creativity',
  },
];

const testimonials = [
  {
    name: 'Sarah Johnson',
    role: 'Wedding Planner',
    content: 'The wedding invitations were absolutely perfect! The quality exceeded our expectations and the attention to detail was incredible.',
    rating: 5,
  },
  {
    name: 'Michael Chen',
    role: 'Small Business Owner',
    content: 'Creative Business helped us create amazing product labels that really make our brand stand out. Highly recommended!',
    rating: 5,
  },
  {
    name: 'Emily Rodriguez',
    role: 'Event Coordinator',
    content: 'From gift tags to custom stickers, everything was perfect for our corporate event. Professional service and beautiful results.',
    rating: 5,
  },
];

export default function HomePage() {
  return (
      <MainLayout showBreadcrumbs={false}>
        {/* Hero Section */}
        <section className="relative overflow-hidden bg-gradient-creative">
          <div className="container mx-auto px-4 py-20 lg:py-32">
            <div className="grid lg:grid-cols-2 gap-12 items-center">
              <motion.div
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ duration: 0.6 }}
                  className="space-y-8"
              >
                <div className="space-y-4">
                  <motion.h1
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.6, delay: 0.1 }}
                      className="text-4xl lg:text-6xl font-bold text-foreground leading-tight"
                  >
                    Transform Your{' '}
                    <span className="text-gradient">Creative Vision</span>
                  </motion.h1>
                  <motion.p
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.6, delay: 0.2 }}
                      className="text-xl text-muted-foreground leading-relaxed"
                  >
                    Professional labels, invitations, stickers, and custom printing
                    services for every occasion. Quality craftsmanship meets creative excellence.
                  </motion.p>
                </div>

                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6, delay: 0.3 }}
                    className="flex flex-col sm:flex-row gap-4"
                >
                  {/* ✅ FIXED: Use Button's built-in href prop instead of wrapping with Link */}
                  <Button
                      href="/products"
                      size="lg"
                      className="w-full sm:w-auto"
                      rightIcon={<ArrowRight className="h-4 w-4" />}
                  >
                    Browse Products
                  </Button>
                  <Button
                      href="/services/custom-design"
                      variant="outline"
                      size="lg"
                      className="w-full sm:w-auto"
                  >
                    Custom Design
                  </Button>
                </motion.div>

                <motion.div
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.6, delay: 0.4 }}
                    className="flex items-center gap-6 pt-4"
                >
                  <div className="flex items-center gap-1">
                    {[...Array(5)].map((_, i) => (
                        <Star key={i} className="h-5 w-5 fill-primary text-primary" />
                    ))}
                  </div>
                  <div className="text-sm text-muted-foreground">
                    <span className="font-semibold text-foreground">4.9/5</span> from 500+ reviews
                  </div>
                </motion.div>
              </motion.div>

              <motion.div
                  initial={{ opacity: 0, x: 20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ duration: 0.6, delay: 0.2 }}
                  className="relative"
              >
                <div className="aspect-square rounded-2xl bg-white/20 backdrop-blur-sm border border-white/30 p-8 flex items-center justify-center">
                  <div className="text-center space-y-4">
                    <div className="w-32 h-32 bg-primary/20 rounded-full flex items-center justify-center mx-auto">
                      <Sparkles className="h-16 w-16 text-primary" />
                    </div>
                    <p className="text-lg font-medium text-foreground">
                      Your creativity, our expertise
                    </p>
                  </div>
                </div>
              </motion.div>
            </div>
          </div>
        </section>

        {/* Product Categories */}
        <section className="py-20 bg-background">
          <div className="container mx-auto px-4">
            <div className="text-center space-y-4 mb-16">
              <h2 className="text-3xl lg:text-4xl font-bold text-foreground">
                Our Product Categories
              </h2>
              <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                Discover our wide range of professional printing services designed to bring your creative projects to life
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              {productCategories.map((category, index) => (
                  <motion.div
                      key={category.href}
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.5, delay: index * 0.1 }}
                  >
                    {/* ✅ FIXED: Use Button instead of Link + Card combination */}
                    <Button
                        href={category.href}
                        variant="ghost"
                        className="h-auto w-full p-0 group"
                    >
                      <Card className="h-full card-hover cursor-pointer w-full">
                        <CardContent className="p-6 text-center space-y-4">
                          <div className={`w-16 h-16 rounded-2xl bg-gradient-to-br ${category.color} mx-auto flex items-center justify-center text-2xl`}>
                            {category.icon}
                          </div>
                          <div className="space-y-2">
                            <h3 className="text-xl font-semibold text-foreground group-hover:text-primary transition-colors">
                              {category.title}
                            </h3>
                            <p className="text-muted-foreground">
                              {category.description}
                            </p>
                          </div>
                        </CardContent>
                      </Card>
                    </Button>
                  </motion.div>
              ))}
            </div>
          </div>
        </section>

        {/* Features */}
        <section className="py-20 bg-muted/30">
          <div className="container mx-auto px-4">
            <div className="text-center space-y-4 mb-16">
              <h2 className="text-3xl lg:text-4xl font-bold text-foreground">
                Why Choose Creative Business?
              </h2>
              <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                We're committed to delivering exceptional quality and service for all your creative printing needs
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
              {features.map((feature, index) => {
                const Icon = feature.icon;
                return (
                    <motion.div
                        key={feature.title}
                        initial={{ opacity: 0, y: 20 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ duration: 0.5, delay: index * 0.1 }}
                        className="text-center space-y-4"
                    >
                      <div className="w-16 h-16 bg-primary/10 rounded-2xl flex items-center justify-center mx-auto">
                        <Icon className="h-8 w-8 text-primary" />
                      </div>
                      <div className="space-y-2">
                        <h3 className="text-xl font-semibold text-foreground">
                          {feature.title}
                        </h3>
                        <p className="text-muted-foreground">
                          {feature.description}
                        </p>
                      </div>
                    </motion.div>
                );
              })}
            </div>
          </div>
        </section>

        {/* Testimonials */}
        <section className="py-20 bg-background">
          <div className="container mx-auto px-4">
            <div className="text-center space-y-4 mb-16">
              <h2 className="text-3xl lg:text-4xl font-bold text-foreground">
                What Our Customers Say
              </h2>
              <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
                Don't just take our word for it - hear from some of our satisfied customers
              </p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
              {testimonials.map((testimonial, index) => (
                  <motion.div
                      key={testimonial.name}
                      initial={{ opacity: 0, y: 20 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.5, delay: index * 0.1 }}
                  >
                    <Card className="h-full">
                      <CardContent className="p-6 space-y-4">
                        <div className="flex items-center gap-1">
                          {[...Array(testimonial.rating)].map((_, i) => (
                              <Star key={i} className="h-4 w-4 fill-primary text-primary" />
                          ))}
                        </div>
                        <p className="text-muted-foreground italic">
                          "{testimonial.content}"
                        </p>
                        <div>
                          <div className="font-semibold text-foreground">
                            {testimonial.name}
                          </div>
                          <div className="text-sm text-muted-foreground">
                            {testimonial.role}
                          </div>
                        </div>
                      </CardContent>
                    </Card>
                  </motion.div>
              ))}
            </div>
          </div>
        </section>

        {/* CTA Section */}
        <section className="py-20 bg-gradient-to-r from-primary to-primary/80">
          <div className="container mx-auto px-4 text-center">
            <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6 }}
                className="space-y-8"
            >
              <div className="space-y-4">
                <h2 className="text-3xl lg:text-4xl font-bold text-primary-foreground">
                  Ready to Bring Your Vision to Life?
                </h2>
                <p className="text-xl text-primary-foreground/90 max-w-2xl mx-auto">
                  Start your creative project today with our professional printing services.
                  Quality, creativity, and exceptional service guaranteed.
                </p>
              </div>

              <div className="flex flex-col sm:flex-row gap-4 justify-center">
                {/* ✅ FIXED: Use Button's built-in href prop */}
                <Button
                    href="/products"
                    variant="secondary"
                    size="lg"
                    className="w-full sm:w-auto"
                    rightIcon={<ArrowRight className="h-4 w-4" />}
                >
                  Start Shopping
                </Button>
                <Button
                    href="/services/custom-design"
                    variant="outline"
                    size="lg"
                    className="w-full sm:w-auto bg-transparent border-primary-foreground text-primary-foreground hover:bg-primary-foreground hover:text-primary"
                >
                  Get Custom Quote
                </Button>
              </div>

              <div className="flex items-center justify-center gap-8 pt-8 text-primary-foreground/80">
                <div className="text-center">
                  <div className="text-2xl font-bold">500+</div>
                  <div className="text-sm">Happy Customers</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold">10k+</div>
                  <div className="text-sm">Projects Completed</div>
                </div>
                <div className="text-center">
                  <div className="text-2xl font-bold">99%</div>
                  <div className="text-sm">Satisfaction Rate</div>
                </div>
              </div>
            </motion.div>
          </div>
        </section>
      </MainLayout>
  );
}