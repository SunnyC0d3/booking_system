export function HeroSkeleton() {
    return (
        <section className="relative overflow-hidden bg-gradient-creative">
            <div className="container mx-auto px-4 py-20 lg:py-32">
                <div className="grid lg:grid-cols-2 gap-12 items-center">
                    <div className="space-y-8">
                        <div className="space-y-4">
                            <div className="h-12 lg:h-16 bg-gray-200 rounded-md animate-pulse" />
                            <div className="h-12 lg:h-16 bg-gray-200 rounded-md animate-pulse w-3/4" />
                            <div className="h-6 bg-gray-200 rounded-md animate-pulse w-5/6" />
                            <div className="h-6 bg-gray-200 rounded-md animate-pulse w-4/6" />
                        </div>
                        <div className="flex flex-col sm:flex-row gap-4">
                            <div className="h-12 w-40 bg-gray-200 rounded-md animate-pulse" />
                            <div className="h-12 w-32 bg-gray-200 rounded-md animate-pulse" />
                        </div>
                        <div className="flex items-center gap-6 pt-4">
                            <div className="flex items-center gap-1">
                                {[...Array(5)].map((_, i) => (
                                    <div key={i} className="h-5 w-5 bg-gray-200 rounded animate-pulse" />
                                ))}
                            </div>
                            <div className="h-4 w-32 bg-gray-200 rounded animate-pulse" />
                        </div>
                    </div>
                    <div className="aspect-square rounded-2xl bg-gray-200 animate-pulse" />
                </div>
            </div>
        </section>
    );
}

export function CategoriesSkeleton() {
    return (
        <section className="py-20 bg-background">
            <div className="container mx-auto px-4">
                <div className="text-center space-y-4 mb-16">
                    <div className="h-10 w-96 mx-auto bg-gray-200 rounded animate-pulse" />
                    <div className="h-6 w-128 mx-auto bg-gray-200 rounded animate-pulse" />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    {[...Array(6)].map((_, i) => (
                        <div key={i} className="h-64 bg-gray-200 rounded-xl animate-pulse" />
                    ))}
                </div>
            </div>
        </section>
    );
}

export function FeaturesSkeleton() {
    return (
        <section className="py-20 bg-muted/30">
            <div className="container mx-auto px-4">
                <div className="text-center space-y-4 mb-16">
                    <div className="h-10 w-80 mx-auto bg-gray-200 rounded animate-pulse" />
                    <div className="h-6 w-96 mx-auto bg-gray-200 rounded animate-pulse" />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    {[...Array(4)].map((_, i) => (
                        <div key={i} className="text-center space-y-4">
                            <div className="w-16 h-16 bg-gray-200 rounded-2xl mx-auto animate-pulse" />
                            <div className="space-y-2">
                                <div className="h-6 w-32 mx-auto bg-gray-200 rounded animate-pulse" />
                                <div className="h-4 w-48 mx-auto bg-gray-200 rounded animate-pulse" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}