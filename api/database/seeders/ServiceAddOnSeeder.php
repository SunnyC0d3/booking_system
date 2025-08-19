<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceAddOn;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceAddOnSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $services = Service::all();

            foreach ($services as $service) {
                $this->createAddOnsForService($service);
            }
        });
    }

    private function createAddOnsForService(Service $service): void
    {
        $addOns = $this->getAddOnsByServiceType($service->name);

        foreach ($addOns as $addOnData) {
            ServiceAddOn::create(array_merge($addOnData, [
                'service_id' => $service->id,
                'is_active' => true,
                'sort_order' => $addOnData['sort_order'] ?? 0,
            ]));
        }
    }

    private function getAddOnsByServiceType(string $serviceName): array
    {
        // Common add-ons for balloon arch services
        if (str_contains(strtolower($serviceName), 'arch')) {
            return [
                [
                    'name' => 'Premium Metallic Balloons',
                    'description' => 'Upgrade to premium metallic finish balloons for extra shine and elegance',
                    'price' => 1500, // £15
                    'duration_minutes' => 15,
                    'is_required' => false,
                    'max_quantity' => 3,
                    'sort_order' => 1,
                    'metadata' => ['colors' => ['gold', 'silver', 'rose_gold', 'copper']],
                ],
                [
                    'name' => 'LED Light Strips',
                    'description' => 'Add battery-powered LED light strips to illuminate your arch',
                    'price' => 2500, // £25
                    'duration_minutes' => 20,
                    'is_required' => false,
                    'max_quantity' => 2,
                    'sort_order' => 2,
                    'metadata' => ['battery_life' => '8-10 hours', 'colors' => ['warm_white', 'cool_white', 'multicolor']],
                ],
                [
                    'name' => 'Floral Accents',
                    'description' => 'Fresh or silk flowers integrated into the balloon arch design',
                    'price' => 3500, // £35
                    'duration_minutes' => 30,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 3,
                    'metadata' => ['flower_types' => ['roses', 'eucalyptus', 'baby_breath', 'custom']],
                ],
                [
                    'name' => 'Organic Balloon Styling',
                    'description' => 'Upgrade to organic, irregular balloon arrangement for modern look',
                    'price' => 2000, // £20
                    'duration_minutes' => 25,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 4,
                    'metadata' => ['style' => 'organic', 'difficulty' => 'advanced'],
                ],
            ];
        }

        // Add-ons for table centerpieces
        if (str_contains(strtolower($serviceName), 'centerpiece')) {
            return [
                [
                    'name' => 'Weighted Base Upgrade',
                    'description' => 'Heavy decorative base to prevent centerpiece movement',
                    'price' => 800, // £8
                    'duration_minutes' => 5,
                    'is_required' => false,
                    'max_quantity' => 10,
                    'sort_order' => 1,
                    'metadata' => ['weight' => '2kg', 'styles' => ['marble', 'crystal', 'metal']],
                ],
                [
                    'name' => 'Number Balloons',
                    'description' => 'Add age or anniversary number balloons to centerpiece',
                    'price' => 1200, // £12
                    'duration_minutes' => 10,
                    'is_required' => false,
                    'max_quantity' => 5,
                    'sort_order' => 2,
                    'metadata' => ['sizes' => ['16_inch', '32_inch'], 'finishes' => ['standard', 'metallic', 'holographic']],
                ],
                [
                    'name' => 'Table Runner Coordination',
                    'description' => 'Color-coordinated table runner to complement balloon colors',
                    'price' => 1500, // £15
                    'duration_minutes' => 5,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 3,
                    'metadata' => ['materials' => ['satin', 'organza', 'burlap', 'sequin']],
                ],
            ];
        }

        // Add-ons for photo backdrops
        if (str_contains(strtolower($serviceName), 'backdrop')) {
            return [
                [
                    'name' => 'Custom Name/Text Banner',
                    'description' => 'Personalized banner with custom text for your backdrop',
                    'price' => 2000, // £20
                    'duration_minutes' => 15,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 1,
                    'metadata' => ['max_characters' => 25, 'fonts' => ['script', 'modern', 'classic']],
                ],
                [
                    'name' => 'Props Package',
                    'description' => 'Fun photo props to complement your backdrop theme',
                    'price' => 1800, // £18
                    'duration_minutes' => 10,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 2,
                    'metadata' => ['items_included' => 8, 'themes' => ['birthday', 'wedding', 'baby_shower', 'graduation']],
                ],
                [
                    'name' => 'Professional Backdrop Stand',
                    'description' => 'Sturdy professional backdrop stand for stability',
                    'price' => 1500, // £15
                    'duration_minutes' => 10,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 3,
                    'metadata' => ['height_adjustable' => true, 'max_width' => '3_meters'],
                ],
            ];
        }

        // Default add-ons for consultation services
        if (str_contains(strtolower($serviceName), 'consultation')) {
            return [
                [
                    'name' => 'Extended Consultation',
                    'description' => 'Extend consultation time for complex event planning',
                    'price' => 2500, // £25
                    'duration_minutes' => 30,
                    'is_required' => false,
                    'max_quantity' => 2,
                    'sort_order' => 1,
                    'metadata' => ['includes' => ['mood_board', 'color_swatches', 'layout_planning']],
                ],
                [
                    'name' => 'Venue Visit',
                    'description' => 'On-site venue visit to assess setup requirements',
                    'price' => 5000, // £50
                    'duration_minutes' => 90,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 2,
                    'metadata' => ['travel_included' => 'within_m25', 'assessment_report' => true],
                ],
                [
                    'name' => 'Digital Mockup',
                    'description' => 'Professional digital mockup of your decoration design',
                    'price' => 3000, // £30
                    'duration_minutes' => 60,
                    'is_required' => false,
                    'max_quantity' => 1,
                    'sort_order' => 3,
                    'metadata' => ['revisions_included' => 2, 'formats' => ['pdf', 'jpg', 'png']],
                ],
            ];
        }

        // Generic add-ons for other services
        return [
            [
                'name' => 'Same Day Setup',
                'description' => 'Premium same-day setup service (subject to availability)',
                'price' => 2000, // £20
                'duration_minutes' => 0,
                'is_required' => false,
                'max_quantity' => 1,
                'sort_order' => 1,
                'metadata' => ['availability' => 'subject_to_schedule', 'booking_deadline' => '10am_same_day'],
            ],
            [
                'name' => 'Photography Service',
                'description' => 'Professional photography of your decorated event',
                'price' => 8000, // £80
                'duration_minutes' => 60,
                'is_required' => false,
                'max_quantity' => 1,
                'sort_order' => 2,
                'metadata' => ['photos_included' => 20, 'editing_included' => true, 'delivery' => '48_hours'],
            ],
            [
                'name' => 'Extended Breakdown Service',
                'description' => 'Return next day to pack down decorations',
                'price' => 1500, // £15
                'duration_minutes' => 30,
                'is_required' => false,
                'max_quantity' => 1,
                'sort_order' => 3,
                'metadata' => ['return_within' => '24_hours', 'includes' => 'balloon_disposal'],
            ],
        ];
    }
}
