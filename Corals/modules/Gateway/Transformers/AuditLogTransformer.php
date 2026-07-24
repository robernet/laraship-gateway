<?php

namespace Corals\Modules\Gateway\Transformers;

use Corals\Foundation\Transformers\BaseTransformer;
use Corals\Modules\Gateway\Models\AuditLog;

class AuditLogTransformer extends BaseTransformer
{
    public function transform(AuditLog $auditLog)
    {
        return parent::transformResponse([
            'id' => $auditLog->id,
            'actor' => $auditLog->actor,
            'action' => $auditLog->action,
            'subject_type' => $auditLog->subject_type,
            'subject_id' => $auditLog->subject_id,
            'payload' => json_encode($auditLog->payload),
            'created_at' => format_date_time($auditLog->created_at),
        ]);
    }
}
