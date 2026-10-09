<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\MorphTo;
class CostCenterApprovalStep extends Model { public const SUPERSEDED = 'SUPERSEDED'; protected $fillable=['step_order','round','cost_center_id','responsible_user_id','principal_user_id','source','status','acted_by_user_id','approval_delegation_id','comments','acted_at']; protected $casts=['acted_at'=>'datetime','round'=>'integer']; public function approvable(): MorphTo { return $this->morphTo(); } }
