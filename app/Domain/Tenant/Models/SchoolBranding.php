<?php

namespace App\Domain\Tenant\Models;

use App\Infrastructure\Tenant\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolBranding extends Model
{
    use HasFactory, BelongsToSchool;

    protected $table = 'school_branding';

    protected $fillable = [
        'school_id',
        'display_name',
        'short_name',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'menu_color',
        'login_message',
        'welcome_text',
        'theme',
        'custom_css_tokens',
    ];

    protected $casts = [
        'custom_css_tokens' => 'array',
    ];
}
