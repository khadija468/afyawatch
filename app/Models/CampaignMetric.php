<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignMetric extends Model
{
    protected $fillable = [
        'metric_date', 'platform', 'impressions', 'reach', 'engagements', 'link_clicks', 'shares',
    ];
}
