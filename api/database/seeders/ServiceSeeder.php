<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $services = $this->getServiceData();

            foreach ($services as $serviceData) {
                Service::create($serviceData);
            }
        });
    }

    private function getServiceData(): array
    {
        return [
            [
                'name' => 'Balloon Arch Design',
                'description' => 'Custom balloon arch creation for weddings, parties, and corporate events. Our professional designers create stunning balloon arches in your chosen colors and theme. Perfect for photo opportunities and venue decoration.',
                'short_description' => 'Custom balloon arch design and installation',
                'category' => 'balloon_arches',
                'base_price' => 12000, // £120 in pence
                'duration_minutes' => 180, // 3 hours
                'buffer_minutes' => 30,
                'max_advance_booking_days' => 90,
                'min_advance_booking_hours' => 48,
                'requires_deposit' => true,
                'deposit_percentage' => 30.00,
                'is_active' => true,
                'is_bookable' => true,
                'requires_consultation' => true,
                'consultation_duration_minutes' => 45,
                'cancellation_policy' => 'Cancellations with less than 48 hours notice may incur a 50% fee.',
                'preparation_notes' => 'Please ensure venue access is available 1 hour before installation time.',
                'metadata' => [
                    'skill_level' => 'advanced',
                    'equipment_required' => ['helium_tank', 'arch_frame', 'balloons', 'tools'],
                    'popular_themes' => ['wedding', 'birthday', 'corporate', 'baby_shower'],
                    'size_options' => ['small (2m)', 'medium (3m)', 'large (4m)', 'custom'],
                    'max_bookings_per_day' => 3,
                    'max_bookings_per_week' => 15,
                    'requires_travel_time' => true,
                    'travel_time_minutes' => 30,
                    'auto_confirm_bookings' => false,
                ],
            ],
            [
                'name' => 'Table Centerpieces',
                'description' => 'Elegant balloon centerpieces for dining tables, reception tables, and event spaces. Available in various heights and styles to complement your event theme without obstructing conversation.',
                'short_description' => 'Balloon table centerpieces for events',
                'category' => 'centerpieces',
                'base_price' => 2500, // £25 per centerpiece in pence
                'duration_minutes' => 45, // Per centerpiece
                'buffer_minutes' => 15,
                'max_advance_booking_days' => 60,
                'min_advance_booking_hours' => 24,
                'requires_deposit' => true,
                'deposit_percentage' => 25.00,
                'is_active' => true,
                'is_bookable' => true,
                'requires_consultation' => false,
                'consultation_duration_minutes' => 30,
                'cancellation_policy' => 'Cancellations with less than 24 hours notice may incur a 25% fee.',
                'preparation_notes' => 'Tables should be set up and accessible 30 minutes before installation.',
                'metadata' => [
                    'skill_level' => 'intermediate',
                    'equipment_required' => ['balloons', 'weights', 'ribbon'],
                    'height_options' => ['low (30cm)', 'medium (50cm)', 'tall (70cm)'],
                    'quantity_pricing' => true,
                    'max_bookings_per_day' => 8,
                    'max_bookings_per_week' => 40,
                    'requires_travel_time' => true,
                    'travel_time_minutes' => 15,
                    'auto_confirm_bookings' => false,
                ],
            ],
            [
                'name' => 'Balloon Garland Installation',
                'description' => 'Organic balloon garlands perfect for backdrops, staircases, and wall features. Custom color combinations and organic shapes create stunning visual impact.',
                'short_description' => 'Organic balloon garland design and installation',
                'category' => 'garlands',
                'base_price' => 8000, // £80 in pence
                'duration_minutes' => 120,
                'buffer_minutes' => 30,
                'max_advance_booking_days' => 75,
                'min_advance_booking_hours' => 36,
                'requires_deposit' => true,
                'deposit_percentage' => 30.00,
                'is_active' => true,
                'is_bookable' => true,
                'requires_consultation' => true,
                'consultation_duration_minutes' => 40,
                'cancellation_policy' => 'Cancellations with less than 36 hours notice may incur a 40% fee.',
                'preparation_notes' => 'Installation area must be cleared and accessible.',
                'metadata' => [
                    'skill_level' => 'advanced',
                    'equipment_required' => ['balloons', 'fishing_line', 'command_hooks', 'balloon_pump'],
                    'style_options' => ['organic', 'structured', 'cascading'],
                    'length_pricing' => 'per_meter',
                    'max_bookings_per_day' => 4,
                    'max_bookings_per_week' => 20,
                    'requires_travel_time' => true,
                    'travel_time_minutes' => 25,
                    'auto_confirm_bookings' => false,
                ],
            ],
            [
                'name' => 'Balloon Bouquet Delivery',
                'description' => 'Professional balloon bouquets delivered to your location. Perfect for birthdays, congratulations, or surprise gifts. Available in various themes and color schemes.',
                'short_description' => 'Custom balloon bouquet with delivery',
                'category' => 'bouquets',
                'base_price' => 3500, // £35 in pence
                'duration_minutes' => 30, // Delivery time
                'buffer_minutes' => 15,
                'max_advance_booking_days' => 30,
                'min_advance_booking_hours' => 4,
                'requires_deposit' => false,
                'deposit_percentage' => 0.00,
                'is_active' => true,
                'is_bookable' => true,
                'requires_consultation' => false,
                'consultation_duration_minutes' => 15,
                'cancellation_policy' => 'Same-day cancellations may incur full charge.',
                'preparation_notes' => 'Recipient should be available at delivery address.',
                'metadata' => [
                    'skill_level' => 'basic',
                    'equipment_required' => ['balloons', 'ribbon', 'weights', 'delivery_bag'],
                    'delivery_zones' => ['central_london', 'greater_london'],
                    'rush_delivery_available' => true,
                    'max_bookings_per_day' => 12,
                    'max_bookings_per_week' => 60,
                    'requires_travel_time' => true,
                    'travel_time_minutes' => 20,
                    'auto_confirm_bookings' => true,
                ],
            ],
            [
                'name' => 'Event Backdrop Design',
                'description' => 'Large-scale balloon backdrops for photo opportunities, stage decoration, and event focal points. Custom designs to match your event theme and branding.',
                'short_description' => 'Large balloon backdrop for events',
                'category' => 'backdrops',
                'base_price' => 15000, // £150 in pence
                'duration_minutes' => 240, // 4 hours
                'buffer_minutes' => 45,
                'max_advance_booking_days' => 120,
                'min_advance_booking_hours' => 72,
                'requires_deposit' => true,
                'deposit_percentage' => 40.00,
                'is_active' => true,
                'is_bookable' => true,
                'requires_consultation' => true,
                'consultation_duration_minutes' => 60,
                'cancellation_policy' => 'Cancellations with less than 72 hours notice may incur a 60% fee.',
                'preparation_notes' => 'Large setup area required, ensure 4+ hour access window.',
                'terms_and_conditions' => 'Custom designs require approval before installation.',
                'metadata' => [
                    'skill_level' => 'expert',
                    'equipment_required' => ['balloons', 'frame_structure', 'helium_tank', 'professional_tools'],
                    'size_options' => ['small (2x2m)', 'medium (3x2.5m)', 'large (4x3m)', 'extra_large (custom)'],
                    'branding_available' => true,
                    'max_bookings_per_day' => 2,
                    'max_bookings_per_week' => 8,
                    'requires_travel_time' => true,
                    'travel_time_minutes' => 45,
                    'auto_confirm_bookings' => false,
                ],
            ],
            [
                'name' => 'Design Consultation',
                'description' => 'Professional consultation service to plan your balloon decoration needs. Includes design concepts, color schemes, venue assessment, and detailed quotations.',
                'short_description' => 'Professional balloon decoration consultation',
                'category' => 'consultations',
                'base_price' => 5000, // £50 in pence
                'duration_minutes' => 60,
                'buffer_minutes' => 15,
                'max_advance_booking_days' => 90,
                'min_advance_booking_hours' => 12,
                'requires_deposit' => false,
                'deposit_percentage' => 0.00,
                'is_active' => true,
                'is_bookable' => true,
                'requires_consultation' => false, // This IS the consultation
                'consultation_duration_minutes' => 60,
                'cancellation_policy' => 'Consultations can be rescheduled with 12 hours notice.',
                'preparation_notes' => 'Please prepare a list of your requirements and any inspiration photos.',
                'metadata' => [
                    'skill_level' => 'expert',
                    'equipment_required' => ['portfolio', 'measurement_tools', 'design_software'],
                    'consultation_types' => ['venue_visit', 'virtual', 'studio_meeting'],
                    'includes_quote' => true,
                    'max_bookings_per_day' => 6,
                    'max_bookings_per_week' => 30,
                    'requires_travel_time' => false,
                    'travel_time_minutes' => 0,
                    'auto_confirm_bookings' => true,
                ],
            ],
            [
                'name' => 'Balloon Ceiling Installation',
                'description' => 'Professional ceiling balloon installations including floating balloons, hanging clusters, and ceiling garlands. Perfect for transforming venue atmosphere.',
                'short_description' => 'Balloon ceiling decoration and installation',
                'category' => 'ceiling_installations',
                'base_price' => 10000, // £100 in pence
                'duration_minutes' => 180,
                'buffer_minutes' => 30,
                'max_advance_booking_days' => 90,
                'min_advance_booking_hours' => 48,
                'requires_deposit' => true,
                'deposit_percentage' => 35.00,
                'is_active' => true,
                'is_bookable' => true,
                'requires_consultation' => true,
                'consultation_duration_minutes' => 45,
                'cancellation_policy' => 'Ceiling installations require 48 hours notice for cancellation.',
                'preparation_notes' => 'Venue must provide safe ceiling access and appropriate ladders/scaffolding.',
                'terms_and_conditions' => 'Venue safety assessment required before installation.',
                'metadata' => [
                    'skill_level' => 'advanced',
                    'equipment_required' => ['helium_tank', 'balloon_pump', 'ceiling_hooks', 'safety_equipment'],
                    'ceiling_types' => ['floating_balloons', 'hanging_clusters', 'ceiling_garlands'],
                    'venue_requirements' => 'ceiling_access_required',
                    'max_bookings_per_day' => 3,
                    'max_bookings_per_week' => 12,
                    'requires_travel_time' => true,
                    'travel_time_minutes' => 30,
                    'auto_confirm_bookings' => false,
                ],
            ],
        ];
    }
}
