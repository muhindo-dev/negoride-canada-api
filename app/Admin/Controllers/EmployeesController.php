<?php

namespace App\Admin\Controllers;

use App\Models\Trip;
use App\Models\TripBooking;
use Carbon\Carbon;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Controllers\AdminController;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Layout\Content;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Hash;

class EmployeesController extends AdminController
{
    protected $title = 'Drivers & Users Management';

    /**
     * Index page
     */
    public function index(Content $content)
    {
        return $content
            ->title('Drivers & Users')
            ->description('Manage all users and driver applications')
            ->row($this->statsRow())
            ->body($this->grid());
    }

    /**
     * Stats cards
     */
    protected function statsRow()
    {
        $totalUsers = Administrator::count();
        $activeDrivers = Administrator::where('user_type', 'Driver')->where('status', '1')->count();
        $pendingDrivers = Administrator::where('status', '2')->count();
        $onlineDrivers = Administrator::where('ready_for_trip', 'Yes')->count();

        $html = '<div class="row" style="margin-bottom: 20px;">';
        
        $html .= '<div class="col-md-3"><div class="info-box bg-blue"><span class="info-box-icon"><i class="fa fa-users"></i></span>';
        $html .= '<div class="info-box-content"><span class="info-box-text">Total Users</span>';
        $html .= '<span class="info-box-number">' . number_format($totalUsers) . '</span></div></div></div>';

        $html .= '<div class="col-md-3"><div class="info-box bg-green"><span class="info-box-icon"><i class="fa fa-car"></i></span>';
        $html .= '<div class="info-box-content"><span class="info-box-text">Active Drivers</span>';
        $html .= '<span class="info-box-number">' . number_format($activeDrivers) . '</span></div></div></div>';

        $html .= '<div class="col-md-3"><div class="info-box bg-yellow"><span class="info-box-icon"><i class="fa fa-clock-o"></i></span>';
        $html .= '<div class="info-box-content"><span class="info-box-text">Pending Approval</span>';
        $html .= '<span class="info-box-number">' . number_format($pendingDrivers) . '</span></div></div></div>';

        $html .= '<div class="col-md-3"><div class="info-box bg-aqua"><span class="info-box-icon"><i class="fa fa-circle"></i></span>';
        $html .= '<div class="info-box-content"><span class="info-box-text">Online Now</span>';
        $html .= '<span class="info-box-number">' . number_format($onlineDrivers) . '</span></div></div></div>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Clean grid table
     */
    protected function grid()
    {
        $grid = new Grid(new Administrator());
        $grid->model()->orderBy('id', 'desc');
        
        $grid->actions(function ($actions) {
            $actions->disableDelete();
        });

        // ID
        $grid->column('id', 'ID')->sortable()->width(60);
        
        // Photo
        $grid->column('avatar', 'Photo')->image('', 40, 40)->width(60);
        
        // Name & Contact
        $grid->column('name', 'Name')->sortable()->width(150);
        $grid->column('phone_number', 'Phone')->width(130);
        $grid->column('email', 'Email')->width(180);
        
        // User Type with badge
        $grid->column('user_type', 'Type')->display(function ($type) {
            $colors = [
                'Super Admin' => 'danger',
                'Admin' => 'warning', 
                'Driver' => 'success',
                'Pending Driver' => 'info',
                'Customer' => 'default'
            ];
            $color = $colors[$type] ?? 'default';
            return "<span class='label label-{$color}'>{$type}</span>";
        })->width(120);
        
        // Status with badge
        $grid->column('status', 'Status')->display(function ($status) {
            $labels = ['1' => 'Active', '2' => 'Pending', '0' => 'Blocked'];
            $colors = ['1' => 'success', '2' => 'warning', '0' => 'danger'];
            $label = $labels[$status] ?? 'Unknown';
            $color = $colors[$status] ?? 'default';
            return "<span class='label label-{$color}'>{$label}</span>";
        })->width(80);
        
        // Services (Car & Delivery only for Canada)
        $grid->column('services', 'Services')->display(function () {
            $services = [];
            
            if ($this->is_car == 'Yes') {
                $approved = $this->is_car_approved == 'Yes';
                $badge = $approved ? 'success' : 'warning';
                $status = $approved ? '✓' : '⏳';
                $services[] = "<span class='label label-{$badge}'>🚗 Rides {$status}</span>";
            }
            
            if ($this->is_delivery == 'Yes') {
                $approved = $this->is_delivery_approved == 'Yes';
                $badge = $approved ? 'success' : 'warning';
                $status = $approved ? '✓' : '⏳';
                $services[] = "<span class='label label-{$badge}'>📦 Delivery {$status}</span>";
            }
            
            return empty($services) ? '-' : implode(' ', $services);
        })->width(180);
        
        // Online status
        $grid->column('ready_for_trip', 'Online')->display(function ($ready) {
            return $ready == 'Yes' 
                ? '<span class="label label-success">🟢 Online</span>' 
                : '<span class="label label-default">Offline</span>';
        })->width(80);
        
        // License info
        $grid->column('driving_license_number', 'License #')->width(120);
        
        // Created date
        $grid->column('created_at', 'Registered')->display(function ($date) {
            return $date ? Carbon::parse($date)->format('M d, Y') : '-';
        })->sortable()->width(100);

        // Quick edit columns for approvals
        $grid->column('is_car_approved', 'Approve Rides')->editable('select', [
            'Yes' => 'Approved',
            'No' => 'Not Approved'
        ])->width(120);
        
        $grid->column('is_delivery_approved', 'Approve Delivery')->editable('select', [
            'Yes' => 'Approved', 
            'No' => 'Not Approved'
        ])->width(120);

        // Filters
        $grid->filter(function ($filter) {
            $filter->disableIdFilter();
            
            $filter->column(1/3, function ($filter) {
                $filter->like('name', 'Name');
                $filter->like('phone_number', 'Phone');
                $filter->like('email', 'Email');
            });
            
            $filter->column(1/3, function ($filter) {
                $filter->equal('user_type', 'User Type')->select([
                    'Super Admin' => 'Super Admin',
                    'Admin' => 'Admin',
                    'Driver' => 'Driver',
                    'Pending Driver' => 'Pending Driver',
                    'Customer' => 'Customer',
                ]);
                $filter->equal('status', 'Status')->select([
                    '1' => 'Active',
                    '2' => 'Pending',
                    '0' => 'Blocked',
                ]);
            });
            
            $filter->column(1/3, function ($filter) {
                $filter->equal('is_car', 'Rides Service')->select(['Yes' => 'Yes', 'No' => 'No']);
                $filter->equal('is_delivery', 'Delivery Service')->select(['Yes' => 'Yes', 'No' => 'No']);
            });
        });

        $grid->quickSearch('name', 'phone_number', 'email', 'driving_license_number')
            ->placeholder('Search by name, phone, email, or license...');

        $grid->paginate(20);
        
        return $grid;
    }

    /**
     * Show detail
     */
    protected function detail($id)
    {
        $show = new Show(Administrator::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('avatar', 'Photo')->image();
        $show->field('name', 'Full Name');
        $show->field('email', 'Email');
        $show->field('phone_number', 'Phone');
        $show->field('user_type', 'User Type');
        $show->field('status', 'Status')->using(['1' => 'Active', '2' => 'Pending', '0' => 'Blocked']);
        
        $show->divider();
        
        $show->field('driving_license_number', 'License Number');
        $show->field('driving_license_photo', 'License Photo')->image();
        
        $show->divider();
        
        $show->field('is_car', 'Rides Service Requested');
        $show->field('is_car_approved', 'Rides Service Approved');
        $show->field('is_delivery', 'Delivery Service Requested');
        $show->field('is_delivery_approved', 'Delivery Service Approved');
        
        $show->divider();
        
        $show->field('created_at', 'Registered');
        $show->field('updated_at', 'Last Updated');

        return $show;
    }

    /**
     * Simple, straightforward form for driver registration
     * No tabs - just clean sections
     */
    protected function form()
    {
        $form = new Form(new Administrator());

        // ==========================================
        // SECTION: Basic Information
        // ==========================================
        $form->html('<div class="box box-solid box-primary">
            <div class="box-header"><h4 class="box-title"><i class="fa fa-user"></i> Basic Information</h4></div>
            <div class="box-body">');
        
        $form->text('name', 'Full Name')->rules('required|max:255')
            ->help('Enter the driver\'s full legal name');
        
        $form->email('email', 'Email Address')->rules('required|email')
            ->help('This will be used for login and notifications');
        
        $form->text('phone_number', 'Phone Number')->rules('required')
            ->help('Canadian phone number (e.g., +1 416 555 0123)');
        
        $form->image('avatar', 'Profile Photo')->uniqueName()
            ->help('Clear photo of the driver\'s face');
        
        $form->date('date_of_birth', 'Date of Birth')
            ->help('Must be at least 18 years old');
        
        $form->radio('sex', 'Gender')->options([
            'Male' => 'Male',
            'Female' => 'Female',
            'Other' => 'Other'
        ])->default('Male');
        
        $form->textarea('current_address', 'Home Address')->rows(2)
            ->help('Full residential address OR GPS coordinates (format: latitude,longitude) for Support Contact location');
        
        // Support Contact GPS Location (for admins acting as support contacts)
        $form->text('support_gps_location', 'Support Contact GPS Location')
            ->placeholder('e.g., 43.6532,-79.3832')
            ->help('Enter GPS coordinates (latitude,longitude) where this support contact is located. This will be shown in the app. Example: 43.6532,-79.3832 for Toronto');

        $form->html('</div></div>');

        // ==========================================
        // SECTION: Account Settings  
        // ==========================================
        $form->html('<div class="box box-solid box-info">
            <div class="box-header"><h4 class="box-title"><i class="fa fa-cog"></i> Account Settings</h4></div>
            <div class="box-body">');
        
        $form->text('username', 'Username')
            ->help('Login username (auto-generated if left empty)');
        
        $form->password('password', 'Password')
            ->help('Minimum 4 characters. Leave empty to keep current password.');
        
        $form->select('user_type', 'Account Type')->options([
            'Customer' => 'Customer (Passenger)',
            'Pending Driver' => 'Pending Driver (Awaiting Approval)',
            'Driver' => 'Approved Driver',
            'Admin' => 'Administrator',
            'Super Admin' => 'Super Administrator',
        ])->default('Customer')->rules('required')
            ->help('Set to "Pending Driver" for new driver applications');
        
        $form->select('status', 'Account Status')->options([
            '1' => 'Active',
            '2' => 'Pending Approval', 
            '0' => 'Blocked/Suspended',
        ])->default('1')->rules('required');
        
        $form->radio('ready_for_trip', 'Availability')->options([
            'Yes' => 'Online (Available for trips)',
            'No' => 'Offline (Not available)',
        ])->default('No');

        $form->html('</div></div>');

        // ==========================================
        // SECTION: Driver's License (Canada)
        // ==========================================
        $form->html('<div class="box box-solid box-warning">
            <div class="box-header"><h4 class="box-title"><i class="fa fa-id-card"></i> Driver\'s License Information</h4></div>
            <div class="box-body">');
        
        $form->text('driving_license_number', 'License Number')
            ->help('Canadian driver\'s license number (The mobile app stores: License # | Issue Date | Expiry Date | Province | SIN)');
        
        $form->image('driving_license_photo', 'License Photo')->uniqueName()
            ->help('Clear photo of the front of the license');

        $form->html('</div></div>');

        // ==========================================
        // SECTION: Services (Canada - Only 2 options)
        // ==========================================
        $form->html('<div class="box box-solid box-success">
            <div class="box-header"><h4 class="box-title"><i class="fa fa-car"></i> Services</h4></div>
            <div class="box-body">');
        
        $form->html('<div class="row">');
        
        // Rides Service
        $form->html('<div class="col-md-6">');
        $form->html('<div class="panel panel-default">
            <div class="panel-heading"><strong>🚗 Private Rides / Special Hire</strong></div>
            <div class="panel-body">');
        
        $form->radio('is_car', 'Service Requested')->options([
            'Yes' => 'Yes, driver wants to offer rides',
            'No' => 'No',
        ])->default('No');
        
        $form->radio('is_car_approved', 'Admin Approval')->options([
            'Yes' => '✅ Approved',
            'No' => '❌ Not Approved',
        ])->default('No')->help('Approve this driver for ride services');
        
        $form->html('</div></div></div>');
        
        // Delivery Service
        $form->html('<div class="col-md-6">');
        $form->html('<div class="panel panel-default">
            <div class="panel-heading"><strong>📦 Courier / Delivery</strong></div>
            <div class="panel-body">');
        
        $form->radio('is_delivery', 'Service Requested')->options([
            'Yes' => 'Yes, driver wants to offer delivery',
            'No' => 'No',
        ])->default('No');
        
        $form->radio('is_delivery_approved', 'Admin Approval')->options([
            'Yes' => '✅ Approved',
            'No' => '❌ Not Approved',
        ])->default('No')->help('Approve this driver for delivery services');
        
        $form->html('</div></div></div>');
        
        $form->html('</div>'); // end row
        $form->html('</div></div>'); // end box

        // ==========================================
        // SECTION: Vehicle Information
        // ==========================================
        $form->html('<div class="box box-solid box-default">
            <div class="box-header"><h4 class="box-title"><i class="fa fa-car"></i> Vehicle Information</h4></div>
            <div class="box-body">');
        
        $form->select('automobile', 'Vehicle Type')->options([
            'car' => 'Car / Sedan',
            'suv' => 'SUV',
            'van' => 'Van',
            'truck' => 'Pickup Truck',
            'motorcycle' => 'Motorcycle',
        ])->default('car');
        
        $form->number('max_passengers', 'Max Passengers')->default(4)
            ->help('Maximum number of passengers the vehicle can carry');

        $form->html('</div></div>');

        // ==========================================
        // SECTION: Support Contact Services (Admin Only)
        // ==========================================
        $form->html('<div class="box box-solid box-danger">
            <div class="box-header"><h4 class="box-title"><i class="fa fa-phone"></i> Support Contact Services (Admin Users Only)</h4></div>
            <div class="box-body">');
        
        $form->html('<p class="text-muted"><strong>Note:</strong> Only users with "Admin" or "Super Admin" account type will appear as Support Contacts in the mobile app. Make sure to set the GPS location above for map display.</p>');
        
        $form->html('<div class="row">');
        
        // Ambulance Service
        $form->html('<div class="col-md-4">');
        $form->radio('is_ambulance', '🚑 Ambulance/Medical')->options([
            '1' => 'Yes - Available',
            'No' => 'No',
        ])->default('No')->help('Medical emergency support');
        $form->html('</div>');
        
        // Police Service
        $form->html('<div class="col-md-4">');
        $form->radio('is_police', '🚔 Police/Security')->options([
            '1' => 'Yes - Available',
            'No' => 'No',
        ])->default('No')->help('Security & police support');
        $form->html('</div>');
        
        // Fire Brigade Service
        $form->html('<div class="col-md-4">');
 
        $form->html('</div>');
        
        $form->html('</div>'); // end row
        
        $form->html('<div class="row" style="margin-top: 15px;">');
        
        // Breakdown Service
        $form->html('<div class="col-md-4">');
        $form->radio('is_breakdown', '🔧 Breakdown/Towing')->options([
            '1' => 'Yes - Available',
            'No' => 'No',
        ])->default('No')->help('Vehicle breakdown support');
        $form->html('</div>');
        
        // Delivery Support
        $form->html('<div class="col-md-4">');
        $form->html('<p><strong>📦 Delivery Support</strong></p><p class="text-muted">Use the Delivery Service option in Driver Services section above.</p>');
        $form->html('</div>');
        
        $form->html('</div>'); // end row
        
        $form->html('</div></div>');

        // Hidden fields removed - now using visible fields above
        $form->hidden('is_boda')->default('No');
        $form->hidden('is_boda_approved')->default('No');
        $form->hidden('is_ambulance_approved')->default('No');
        $form->hidden('is_police_approved')->default('No');
        $form->hidden('is_breakdown')->default('No');
        $form->hidden('is_breakdown_approved')->default('No'); 

        // Form saving logic
        $form->saving(function (Form $form) {
            // Hash password if changed
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = Hash::make($form->password);
            }
            
            // Auto-generate username from email if not provided
            if (!$form->username && $form->email) {
                $form->username = explode('@', $form->email)[0];
            }
            
            // Auto-approve driver when any service is approved
            if ($form->is_car_approved == 'Yes' || $form->is_delivery_approved == 'Yes') {
                $form->user_type = 'Driver';
                $form->status = '1';
            }
            
            // Use support GPS location for current_address if provided (for support contacts)
            if ($form->support_gps_location && preg_match('/^-?\d+\.\d+,-?\d+\.\d+$/', trim($form->support_gps_location))) {
                $form->current_address = trim($form->support_gps_location);
            }
        });

        return $form;
    }

    /**
     * Quick approve action
     */
    public function approve($id)
    {
        $user = Administrator::findOrFail($id);
        $user->status = '1';
        $user->user_type = 'Driver';
        $user->save();

        admin_toastr('Driver approved successfully!', 'success');
        return redirect()->back();
    }

    /**
     * Block user action
     */
    public function block($id)
    {
        $user = Administrator::findOrFail($id);
        $user->status = '0';
        $user->save();

        admin_toastr('User blocked successfully.', 'warning');
        return redirect()->back();
    }

    /**
     * Activate user action
     */
    public function activate($id)
    {
        $user = Administrator::findOrFail($id);
        $user->status = '1';
        $user->save();

        admin_toastr('User activated successfully!', 'success');
        return redirect()->back();
    }

    /**
     * Approve specific service
     */
    public function approveService($id, $service)
    {
        $user = Administrator::findOrFail($id);
        $field = "is_{$service}_approved";
        
        $user->$field = 'Yes';
        $user->save();

        admin_toastr(ucfirst($service) . ' service approved!', 'success');
        return redirect()->back();
    }

    /**
     * Analytics page
     */
    public function analytics(Content $content)
    {
        $stats = [
            'total_users' => Administrator::count(),
            'active_drivers' => Administrator::where('user_type', 'Driver')->where('status', '1')->count(),
            'pending_drivers' => Administrator::where('status', '2')->count(),
            'online_drivers' => Administrator::where('ready_for_trip', 'Yes')->count(),
            'ride_drivers' => Administrator::where('is_car_approved', 'Yes')->count(),
            'delivery_drivers' => Administrator::where('is_delivery_approved', 'Yes')->count(),
        ];

        $html = '<div class="row">';
        
        foreach ($stats as $label => $value) {
            $title = ucwords(str_replace('_', ' ', $label));
            $html .= '<div class="col-md-4"><div class="small-box bg-aqua">';
            $html .= '<div class="inner"><h3>' . number_format($value) . '</h3><p>' . $title . '</p></div>';
            $html .= '<div class="icon"><i class="fa fa-users"></i></div></div></div>';
        }
        
        $html .= '</div>';

        return $content
            ->title('Analytics')
            ->description('Platform statistics')
            ->body($html);
    }

    /**
     * Reports page
     */
    public function reports(Content $content)
    {
        return $content
            ->title('Reports')
            ->description('Generate and export reports')
            ->body('<div class="alert alert-info">Reports feature coming soon.</div>');
    }

    /**
     * Bulk operations page
     */
    public function bulkOperations(Content $content)
    {
        return $content
            ->title('Bulk Operations')
            ->description('Mass updates and batch processing')
            ->body('<div class="alert alert-info">Bulk operations feature coming soon.</div>');
    }
}
