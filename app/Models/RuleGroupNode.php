<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 审计规则分组.
 */
class RuleGroupNode extends Model
{
    protected $table = 'rule_group_node';
    protected $guarded = [];

    public function ruleGroup()
    {
        return $this->belongsTo(RuleGroup::class);
    }
}
