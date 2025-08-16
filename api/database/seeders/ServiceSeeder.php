<?php
namespace Database\Seeders;

use App\Models\Service;
use App\Constants\ServiceStatuses;
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

            $this->command->info('Services created successfully!');
        });
    }

    private function getServiceData(): array
    {
        return [
            [
                'name' => 'Balloon Arch Design',
                'description' => 'Custom balloon arch creation for weddings, parties, and corporate events. Our professional designers create stunning balloon arches in your chosen colors and theme. Perfect for photo opportunities and venue decoration.',
                'short_description' => 'Custom balloon arch design and installation',
                'base_price' => 12000, // £120
                'duration_minutes' => 180, // 3 hours
                'buffer_minutes' => 30,
                'max_advance_booking_days' => 90,
                'min_advance_booking_hours' => 48,
                'requires_deposit' => true,
                'deposit_percentage' => 30.00,
                'status' => ServiceStatuses::ACTIVE,
                'max_bookings_per_day' => 3,
                'max_bookings_per_week' => 15,
                'requires_travel_time' => true,
                'travel_time_minutes' => 30,
                'auto_confirm_bookings' => false,
                'consultation_settings' => [
                    'recommended' => true,
                    'required_for_custom_designs' => true,
                    'duration_minutes' => 45,
                ],
                'metadata' => [
                    'skill_level' => 'advanced',
                    'equipment_required' => ['helium_tank', 'arch_frame', 'balloons', 'tools'],
                    'popular_themes' => ['wedding', 'birthday', 'corporate', 'baby_shower'],
                    'size_options' => ['small (2m)', 'medium (3m)', 'large (4m)', 'custom'],
                ],
            ],
            [
                'name' => 'Table Centerpieces',
                'description' => 'Elegant balloon centerpieces for dining tables, reception tables, and event spaces. Available in various heights and styles to complement your event theme without obstructing conversation.',
                'short_description' => 'Balloon table centerpieces for events',
                'base_price' => 2500, // £25 per centerpiece
                'duration_minutes' => 30, // per centerpiece
                'buffer_minutes' => 15,
                'max_advance_booking_days' => 60,
                'min_advance_booking_hours' => 24,
                'requires_deposit' => false,
                'status' => ServiceStatuses::ACTIVE,
                'max_bookings_per_day' => 8,
                'max_bookings_per_week' => 40,
                'requires_travel_time' => false,
                'auto_confirm_bookings' => true,
                'consultation_settings' => [
                    'recommended' => false,
                    'required_for_custom_designs' => false,
                ],
                'metadata' => [
                    'skill_level' => 'intermediate',
                    'height_options' => ['low (25cm)', 'medium (40cm)', 'high (60cm)'],
                    'style_options' => ['classic', 'modern', 'organic', 'themed'],
                    'quantity_discounts' => ['5+ tables: 10% off', '10+ tables: 15% off'],
                ],
            ],
            [
                'name' => 'Photo Backdrop Setup',
                'description' => 'Professional balloon backdrop installation perfect for photo opportunities at weddings, parties, and corporate events. Includes balloon wall, entrance decoration, or step-and-repeat style backdrops.',
                'short_description' => 'Balloon backdrop for photo opportunities',
                'base_price' => 8500, // £85
                'duration_minutes' => 120, // 2 hours
                'buffer_minutes' => 30,
                'max_advance_booking_days' => 90,
                'min_advance_booking_hours' => 48,
                'requires_deposit' => true,
                'deposit_percentage' => 25.00,
                'status' => ServiceStatuses::ACTIVE,
                'max_bookings_per_day' => 4,
                'max_bookings_per_week' => 20,
                'requires_travel_time' => true,
                'travel_time_minutes' => 20,
                'auto_confirm_bookings' => false,
                'consultation_settings' => [
                    'recommended' => true,
                    'required_for_custom_designs' => true,
                    'duration_minutes' => 30,
                ],
                'metadata' => [
                    'skill_level' => 'advanced',
                    'backdrop_types' => ['wall', 'entrance', 'step_repeat', 'organic'],
                    'size_options' => ['2m x 2m', '3m x 2m', '4m x 2.5m', 'custom'],
                    'lighting_compatible' => true,
                ],
            ],
            [
                'name' => 'Venue Consultation',
                'description' => 'Professional consultation service for event planning and decoration design. Includes venue assessment, color scheme planning, layout design, and comprehensive decoration recommendations.',
                'short_description' => 'Professional event decoration consultation',
                'base_price' => 7500, // £75
                'duration_minutes' => 90,
                'buffer_minutes' => 15,
                'max_advance_booking_days' => 120,
                'min_advance_booking_hours' => 24,
                'requires_deposit' => false,
                'status' => ServiceStatuses::ACTIVE,
                'max_bookings_per_day' => 6,
                'max_bookings_per_week' => 30,
                'requires_travel_time' => true,
                'travel_time_minutes' => 45,
                'auto_confirm_bookings' => true,
                'consultation_settings' => [
                    'is_consultation_service' => true,
                    'includes_followup' => true,
                    'digital_mockup_available' => true,
                ],
                'metadata' => [
                    'skill_level' => 'expert',
                    'includes' => ['venue_assessment', 'color_planning', 'layout_design', 'cost_estimate'],
                    'deliverables' => ['consultation_report', 'mood_board', 'quote'],
                    'followup_included' => '2 weeks',
                ],
            ],
            [
                'name' => 'Event Setup & Styling',
                'description' => 'Complete event decoration service including balloon installations, styling, and coordination. Perfect for clients who want full-service decoration without the planning stress.',
                'short_description' => 'Complete event decoration and styling service',
                'base_price' => 25000, // £250
                'duration_minutes' => 300, // 5 hours
                'buffer_minutes' => 60,
                'max_advance_booking_days' => 180,
                'min_advance_booking_hours' => 72,
                'requires_deposit' => true,
                'deposit_percentage' => 40.00,
                'status' => ServiceStatuses::ACTIVE,
                'max_bookings_per_day' => 1,
                'max_bookings_per_week' => 6,
                'requires_travel_time' => true,
                'travel_time_minutes' => 60,
                'auto_confirm_bookings' => false,
                'consultation_settings' => [
                    'recommended' => true,
                    'required_for_custom_designs' => true,
                    'duration_minutes' => 90,
                    'multiple_consultations' => true,
                ],
                'metadata' => [
                    'skill_level' => 'expert',
                    'includes' => ['design', 'setup', 'styling', 'coordination', 'breakdown'],
                    'team_size' => '2-3 people',
                    'event_types' => ['wedding', 'corporate', 'private_party', 'celebration'],
                ],
            ],
            [
                'name' => 'Balloon Garland Installation',
                'description' => 'Organic balloon garland installation for staircases, mantels, tables, and architectural features. Modern, Instagram-worthy styling with irregular balloon clusters.',
                'short_description' => 'Organic balloon garland installation',
                'base_price' => 6000, // £60
                'duration_minutes' => 90,
                'buffer_minutes' => 20,
                'max_advance_booking_days' => 60,
                'min_advance_booking_hours' => 48,
                'requires_deposit' => true,
                'deposit_percentage' => 20.00,
                'status' => ServiceStatuses::ACTIVE,
                'max_bookings_per_day' => 5,
                'max_bookings_per_week' => 25,
                'requires_travel_time' => true,
                'travel_time_minutes' => 25,
                'auto_confirm_bookings' => false,
                'consultation_settings' => [
                    'recommended' => false,
                    'required_for_custom_designs' => false,
                ],
                'metadata' => [
                    'skill_level' => 'intermediate',
                    'style' => 'organic',
                    'length_options' => ['1m', '2m', '3m', '4m', '5m+'],
                    'trending' => true,
                ],
            ],
        ];
    }
}
'is_default' => true,
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'metadata' => [
    'transport_links' => ['Tube: 5 min walk', 'Bus: Direct routes'],
    'opening_hours' => ['Mon-Fri: 9AM-6PM', 'Sat: 10AM-4PM', 'Sun: Closed'],
    'contact_person' => 'Sarah Johnson',
],
                'venue_details' => [
    'venue_type' => 'studio',
    'setup_requirements' => 'Climate controlled studio with high ceilings. Professional lighting and backdrop setup available.',
    'equipment_available' => 'Professional balloon pumps, helium tanks, assembly tables, lighting equipment, photography backdrops',
    'accessibility_info' => 'Wheelchair accessible entrance and facilities. Lift access to upper floors.',
    'parking_info' => 'Limited street parking. Public car park 2 minutes walk (£8/day weekdays, £5/day weekends)',
    'catering_options' => 'Tea, coffee, and light refreshments available for consultation appointments',
    'max_capacity' => 50,
    'setup_time_minutes' => 30,
    'breakdown_time_minutes' => 20,
    'additional_fee' => 0.00,
    'amenities' => ['Air conditioning', 'Professional lighting', 'Photography area', 'Client seating', 'Storage space'],
    'restrictions' => ['No smoking', 'No outside food during events', 'Maximum 2 hours setup time'],
],
            ],
            [
                'name' => 'North London Venue',
                'address' => '456 Event Avenue',
                'city' => 'London',
                'postcode' => 'N1 7GU',
                'country' => 'United Kingdom',
                'phone' => '+44 20 7946 0959',
                'email' => 'north@balloondesigns.co.uk',
                'is_active' => true,
                'is_default' => false,
                'latitude' => 51.5387,
                'longitude' => -0.1079,
                'metadata' => [
                    'transport_links' => ['Tube: 3 min walk from Angel', 'Bus: Multiple routes'],
                    'opening_hours' => ['Mon-Sat: 8AM-8PM', 'Sun: 10AM-6PM'],
                    'contact_person' => 'Michael Thompson',
                ],
                'venue_details' => [
                    'venue_type' => 'event_space',
                    'setup_requirements' => 'Large open space perfect for event decoration setup and client viewing.',
                    'equipment_available' => 'Basic balloon equipment, tables, chairs for consultations',
                    'accessibility_info' => 'Ground floor access, wheelchair accessible toilets available',
                    'parking_info' => 'Free parking available in rear courtyard (6 spaces)',
                    'catering_options' => 'Basic refreshments, can arrange external catering for large consultations',
                    'max_capacity' => 30,
                    'setup_time_minutes' => 45,
                    'breakdown_time_minutes' => 30,
                    'additional_fee' => 25.00,
                    'amenities' => ['Natural lighting', 'Flexible space', 'Client meeting area', 'Kitchen facilities'],
                    'restrictions' => ['No late evening events after 8PM', 'Advance booking required for weekends'],
                ],
            ],
            [
                'name' => 'Client Location Service',
                'address' => 'Various locations across London',
                'city' => 'London',
                'postcode' => 'VARIOUS',
                'country' => 'United Kingdom',
                'phone' => '+44 20 7946 0960',
                'email' => 'mobile@balloondesigns.co.uk',
                'is_active' => true,
                'is_default' => false,
                'latitude' => 51.5074,
                'longitude' => -0.1278,
                'metadata' => [
                    'service_type' => 'mobile',
                    'coverage_area' => 'Greater London (within M25)',
                    'travel_time' => 'Varies by location',
                    'contact_person' => 'Mobile Team',
                ],
                'venue_details' => [
                    'venue_type' => 'client_location',
                    'setup_requirements' => 'Setup performed at client\'s chosen venue. Venue must have adequate space and access.',
                    'equipment_available' => 'Portable balloon equipment, mobile pumps, transport cases',
                    'accessibility_info' => 'Accessibility dependent on client venue - assessed during consultation',
                    'parking_info' => 'Parking arrangements made with client venue',
                    'catering_options' => 'Not applicable - setup only service',
                    'max_capacity' => 200,
                    'setup_time_minutes' => 60,
                    'breakdown_time_minutes' => 45,
                    'additional_fee' => 50.00,
                    'amenities' => ['Mobile equipment', 'Professional setup team', 'Transport included'],
                    'restrictions' => [
                        'Minimum 3 days advance booking',
                        'Travel charges apply outside M25',
                        'Venue must provide adequate access',
                        'Setup area must be available 1 hour before event'
                    ],
                ],
            ],
            [
                'name' => 'South London Workshop',
                'address' => '789 Craft Lane',
                'city' => 'London',
                'postcode' => 'SE1 9RT',
                'country' => 'United Kingdom',
                'phone' => '+44 20 7946 0961',
                'email' => 'south@balloondesigns.co.uk',
                'is_active' => true,
                'is_default' => false,
                'latitude' => 51.4994,
                'longitude' => -0.1270,
                'metadata' => [
                    'transport_links' => ['Tube: 8 min walk from London Bridge', 'Bus: Regular services'],
                    'opening_hours' => ['Tue-Sat: 9AM-7PM', 'Mon & Sun: By appointment only'],
                    'contact_person' => 'Lisa Chen',
                ],
                'venue_details' => [
                    'venue_type' => 'workshop',
                    'setup_requirements' => 'Working studio for balloon art creation and small-scale event preparation.',
                    'equipment_available' => 'Full workshop setup, storage facilities, design table, helium tanks',
                    'accessibility_info' => 'Ground floor access, adapted toilet facilities',
                    'parking_info' => 'Street parking (pay and display). Loading bay available for large orders',
                    'catering_options' => 'Small kitchen area, basic refreshments available',
                    'max_capacity' => 15,
                    'setup_time_minutes' => 20,
                    'breakdown_time_minutes' => 15,
                    'additional_fee' => 0.00,
                    'amenities' => ['Workshop tools', 'Storage space', 'Design area', 'Good ventilation'],
                    'restrictions' => ['Workshop use only', 'No large events', 'Safety equipment required'],
                ],
            ],
        ];
    }
}
