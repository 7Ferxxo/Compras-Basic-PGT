<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\RequestAttachment;
use App\Models\RequestLog;
use App\Models\Store;
use App\Services\Compras\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PurchaseRequestsController extends Controller
{
    public function __construct(private readonly PurchaseRequestService $service)
    {
    }

    private function allowedStatuses(): array
    {
        return [
            'Borrador',
            'Pendiente comprobante',
            'Enviada al Supervisor',
            'Compra realizada',
            'Completada',
            'Cancelada',
        ];
    }

    private function storeNameExpression(string $storeAlias, string $customAlias): string
    {
        $driver = DB::connection()->getDriverName();
        $concat = $driver === 'sqlite'
            ? "{$storeAlias}.name || ' - ' || {$customAlias}"
            : "CONCAT({$storeAlias}.name, ' - ', {$customAlias})";

        return "CASE
                    WHEN {$customAlias} IS NOT NULL AND {$customAlias} <> ''
                      THEN {$concat}
                    ELSE {$storeAlias}.name
                END AS store_name";
    }

    private function isComprasAuthorized(Request $request): bool
    {
        $expected = trim((string) config('services.compras.admin_token', ''));

        if ($expected === '') {
            return app()->environment('local');
        }

        $got = (string) $request->header('x-compras-token', '');
        if ($got === '') return false;

        return hash_equals($expected, $got);
    }

    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $pageSize = min(50, max(1, (int) $request->query('pageSize', 10)));
        $storeId = $request->query('storeId');
        $status = $request->query('status');
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('purchase_requests as pr')
            ->join('stores as s', 's.id', '=', 'pr.store_id');

        if ($storeId !== null && $storeId !== '') {
            $query->where('pr.store_id', (int) $storeId);
        }
        if ($status !== null && $status !== '') {
            $statuses = collect(explode(',', (string) $status))
                ->map(fn ($s) => trim($s))
                ->filter(fn ($s) => $s !== '')
                ->unique()
                ->values();

            if ($statuses->count() === 1) {
                $query->where('pr.status', (string) $statuses->first());
            } elseif ($statuses->count() > 1) {
                $query->whereIn('pr.status', $statuses->all());
            }
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($sub) use ($like) {
                $sub
                    ->where('pr.client_name', 'like', $like)
                    ->orWhere('pr.client_code', 'like', $like)
                    ->orWhere('pr.code', 'like', $like)
                    ->orWhere('pr.item_link', 'like', $like);
            });
        }

        $total = (int) $query->clone()->count();

        $offset = ($page - 1) * $pageSize;
        $storeNameExpr = $this->storeNameExpression('s', 'pr.store_custom_name');
        $rows = $query
            ->select([
                'pr.id',
                'pr.code',
                'pr.client_name',
                'pr.client_code',
                'pr.status',
                'pr.item_link',
                'pr.quoted_total',
                'pr.residential_charge',
                'pr.american_card_charge',
                'pr.created_at',
                'pr.updated_at',
                DB::raw(
                    "(SELECT a.stored_name
                        FROM request_attachments a
                       WHERE a.request_id = pr.id AND a.type = 'PAYMENT_PROOF'
                       ORDER BY a.uploaded_at DESC
                       LIMIT 1) AS payment_proof_file",
                ),
                DB::raw(
                    "(SELECT COUNT(*)
                        FROM request_attachments a
                       WHERE a.request_id = pr.id AND a.type = 'QUOTE_SCREENSHOT') AS quote_screenshots_count",
                ),
                DB::raw($storeNameExpr),
            ])
            ->orderByDesc('pr.created_at')
            ->limit($pageSize)
            ->offset($offset)
            ->get();

        return response()->json([
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => $pageSize ? (int) ceil($total / $pageSize) : 0,
            ],
        ]);
    }

    public function show(string $id)
    {
        $storeNameExpr = $this->storeNameExpression('s', 'pr.store_custom_name');
        $row = DB::table('purchase_requests as pr')
            ->join('stores as s', 's.id', '=', 'pr.store_id')
            ->leftJoin('store_rules as r', 'r.store_id', '=', 'pr.store_id')
            ->where('pr.id', (int) $id)
            ->select([
                'pr.*',
                DB::raw($storeNameExpr),
                'r.requires_residential_address',
                'r.residential_fee_per_item',
                'r.requires_american_card',
                'r.american_card_surcharge_rate',
            ])
            ->first();

        if (!$row) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $attachments = RequestAttachment::query()
            ->where('request_id', (int) $id)
            ->orderBy('uploaded_at')
            ->get()
            ->map(function (RequestAttachment $a) {
                return [
                    'id' => $a->id,
                    'request_id' => $a->request_id,
                    'type' => $a->type,
                    'original_name' => $a->original_name,
                    'stored_name' => $a->stored_name,
                    'mime_type' => $a->mime_type,
                    'size_bytes' => $a->size_bytes,
                    'uploaded_at' => $a->uploaded_at,
                    'url' => '/uploads/' . rawurlencode($a->stored_name),
                ];
            })
            ->values();

        $logs = RequestLog::query()
            ->where('request_id', (int) $id)
            ->orderBy('created_at')
            ->get();

        $hasAccountPassword = (bool) ($row->account_password_enc ? null);
        unset($row->account_password_enc);

        return response()->json([
            ...((array) $row),
            'hasAccountPassword' => $hasAccountPassword,
            'attachments' => $attachments,
            'logs' => $logs,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clientName' => ['required', 'string', 'max:120'],
            'clientCode' => ['required', 'string', 'max:50'],
            'contactChannel' => ['nullable', 'string', 'max:80'],
            'paymentMethod' => ['nullable', 'string', 'max:20', Rule::in(['Transferencia', 'Yappy', 'Efectivo', 'Tarjeta'])],
            'accountEmail' => ['nullable', 'string', 'max:255'],
            'accountPassword' => ['nullable', 'string', 'max:500'],
            'storeId' => ['required', 'integer', 'min:1'],
            'storeCustomName' => ['nullable', 'string', 'max:255'],
            'itemLink' => ['required', 'string', 'max:2000'],
            'itemOptions' => ['nullable', 'string', 'max:2000'],
            'itemQuantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'quotedTotal' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'quoteScreenshots' => ['required'],
            'quoteScreenshots.*' => ['file', 'max:10240'],
            'paymentProof' => ['nullable', 'file', 'max:10240'],
        ]);

        $storeId = (int) $validated['storeId'];
        if ($storeId === 7 && trim((string) ($validated['storeCustomName'] ? '')) === '') {
            return response()->json(['error' => 'storeCustomName es requerido cuando la tienda es OTROS'], 400);
        }

        if (!Store::query()->whereKey($storeId)->exists()) {
            return response()->json(['error' => 'storeId inválido'], 400);
        }

        $screenshots = $request->file('quoteScreenshots', []);
        if (!is_array($screenshots) || count($screenshots) < 1) {
            return response()->json(['error' => 'quoteScreenshots es requerido (mínimo 1)'], 400);
        }

        $quotedTotal = (float) $validated['quotedTotal'];
        $itemQuantity = (int) ($validated['itemQuantity'] ? 1);
        $charges = $this->service->computeCharges($storeId, $itemQuantity, $quotedTotal);

        $paymentProof = $request->file('paymentProof');
        $status = $paymentProof ? 'Borrador' : 'Pendiente comprobante';
        $code = $this->service->nextCode();

        $passwordEnc = null;
        $accountPassword = trim((string) ($validated['accountPassword'] ? ''));
        if ($accountPassword !== '') {
            $passwordEnc = Crypt::encryptString($accountPassword);
        }

        $purchaseRequest = PurchaseRequest::query()->create([
            'code' => $code,
            'client_name' => $validated['clientName'],
            'client_code' => $validated['clientCode'],
            'contact_channel' => $validated['contactChannel'] ? null,
            'payment_method' => $validated['paymentMethod'] ? null,
            'account_email' => $validated['accountEmail'] ? null,
            'account_password_enc' => $passwordEnc,
            'store_id' => $storeId,
            'store_custom_name' => $storeId === 7 ? ($validated['storeCustomName'] ? null) : null,
            'item_link' => $validated['itemLink'],
            'item_options' => $validated['itemOptions'] ? null,
            'item_quantity' => $itemQuantity,
            'quoted_total' => $quotedTotal,
            'residential_charge' => $charges['residentialCharge'],
            'american_card_charge' => $charges['americanCardCharge'],
            'notes' => $validated['notes'] ? null,
            'status' => $status,
        ]);

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'created',
            'from_status' => null,
            'to_status' => $status,
            'note' => 'Creación',
        ]);

        foreach ($screenshots as $file) {
            $stored = $this->service->storeUpload($file);
            RequestAttachment::query()->create([
                'request_id' => $purchaseRequest->id,
                'type' => 'QUOTE_SCREENSHOT',
                ...$stored,
                'uploaded_at' => now(),
            ]);
        }

        if ($paymentProof) {
            $stored = $this->service->storeUpload($paymentProof);
            RequestAttachment::query()->create([
                'request_id' => $purchaseRequest->id,
                'type' => 'PAYMENT_PROOF',
                ...$stored,
                'uploaded_at' => now(),
            ]);
        }

        return response()->json([
            'id' => $purchaseRequest->id,
            'code' => $code,
            'status' => $status,
        ], 201);
    }

    public function patchStatus(Request $request, string $id)
    {
        $allowed = $this->allowedStatuses();
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:50', Rule::in($allowed)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchaseRequest = PurchaseRequest::query()->find((int) $id);
        if (!$purchaseRequest) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $nextStatus = $validated['status'];

        if (in_array($nextStatus, ['Compra realizada', 'Completada'], true) && !$this->isComprasAuthorized($request)) {
            return response()->json(['error' => 'No autorizado para cambiar a estado de compras'], 403);
        }

        if ($nextStatus === 'Enviada al Supervisor') {
            $hasProof = RequestAttachment::query()
                ->where('request_id', $purchaseRequest->id)
                ->where('type', 'PAYMENT_PROOF')
                ->exists();
            if (!$hasProof) {
                return response()->json(['error' => 'No se puede enviar sin comprobante de pago'], 400);
            }
        }

        $fromStatus = $purchaseRequest->status;
        if ($fromStatus !== $nextStatus && $nextStatus === 'Compra realizada' && $fromStatus !== 'Enviada al Supervisor') {
            return response()->json(['error' => 'Solo se puede marcar Compra realizada después de Enviada al Supervisor'], 400);
        }
        if ($fromStatus !== $nextStatus && $nextStatus === 'Completada' && $fromStatus !== 'Compra realizada') {
            return response()->json(['error' => 'Solo se puede completar después de Compra realizada'], 400);
        }
        $purchaseRequest->status = $nextStatus;
        $purchaseRequest->save();

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'status_change',
            'from_status' => $fromStatus,
            'to_status' => $nextStatus,
            'note' => trim((string) ($validated['note'] ? '')) ?: null,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true, 'status' => $nextStatus]);
    }

    public function sendToSupervisor(Request $request, string $id)
    {
        $purchaseRequest = PurchaseRequest::query()->find((int) $id);
        if (!$purchaseRequest) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $note = trim((string) ($request->input('note') ? '')) ?: null;

        $file = $request->file('paymentProof');
        if ($file) {
            $stored = $this->service->storeUpload($file);
            RequestAttachment::query()->create([
                'request_id' => $purchaseRequest->id,
                'type' => 'PAYMENT_PROOF',
                ...$stored,
                'uploaded_at' => now(),
            ]);
        }

        $hasProof = RequestAttachment::query()
            ->where('request_id', $purchaseRequest->id)
            ->where('type', 'PAYMENT_PROOF')
            ->exists();
        if (!$hasProof) {
            return response()->json(['error' => 'No se puede enviar sin comprobante de pago'], 400);
        }

        $fromStatus = $purchaseRequest->status;
        $purchaseRequest->status = 'Enviada al Supervisor';
        $purchaseRequest->sent_note = $note;
        $purchaseRequest->sent_at = now();
        $purchaseRequest->save();

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'send_to_supervisor',
            'from_status' => $fromStatus,
            'to_status' => 'Enviada al Supervisor',
            'note' => $note,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true, 'status' => 'Enviada al Supervisor']);
    }

    public function uploadAttachment(Request $request, string $id)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:30', Rule::in(['ORDER_DOC', 'PAYMENT_PROOF', 'QUOTE_SCREENSHOT'])],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $purchaseRequest = PurchaseRequest::query()->find((int) $id);
        if (!$purchaseRequest) {
            return response()->json(['error' => 'Solicitud no encontrada'], 404);
        }

        $stored = $this->service->storeUpload($request->file('file'));
        $attachment = RequestAttachment::query()->create([
            'request_id' => $purchaseRequest->id,
            'type' => $validated['type'],
            ...$stored,
            'uploaded_at' => now(),
        ]);

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'attachment_added',
            'from_status' => $purchaseRequest->status,
            'to_status' => $purchaseRequest->status,
            'note' => 'Adjunto: ' . $validated['type'],
            'created_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'attachment_id' => $attachment->id,
        ]);
    }

    public function createFromInvoiceWebhook(Request $request)
    {
        $expectedToken = trim((string) env('FACTURADOR_WEBHOOK_TOKEN', ''));
        if ($expectedToken !== '') {
            $got = (string) ($request->header('x-webhook-token') ? '');
            if ($got === '') {
                $auth = (string) ($request->header('authorization') ? '');
                if (preg_match('/^Bearer\\s+(.+)$/i', $auth, $m)) {
                    $got = (string) ($m[1] ? '');
                } else {
                    $got = $auth;
                }
            }
            if ($got === '' || $got !== $expectedToken) {
                return response()->json(['error' => 'Token de webhook inválido'], 401);
            }
        }

        try {
            $created = $this->service->createFromInvoiceWebhookPayload($request->all());
            return response()->json(['ok' => true, 'id' => $created->id, 'code' => $created->code, 'status' => $created->status], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            Log::error('Error creando solicitud desde webhook de recibo', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error inesperado'], 500);
        }
    }

    public function attachFromReceipt(Request $request, string $reciboId)
    {
        $request->validate([
            'quoteScreenshots' => ['nullable'],
            'quoteScreenshots.*' => ['file', 'max:10240'],
            'paymentProof' => ['nullable', 'file', 'max:10240'],
            'cliente' => ['nullable', 'string', 'max:120'],
            'casillero' => ['nullable', 'string', 'max:50'],
            'metodo_pago' => ['nullable', 'string', 'max:20'],
            'descripcion_compra' => ['nullable', 'string', 'max:2000'],
            'monto_pagado' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'link_producto' => ['nullable', 'string', 'max:2000'],
        ]);

        $receiptId = trim((string) $reciboId);

        $purchaseRequest = PurchaseRequest::query()
            ->where('source_system', 'FACTURADOR_LARAVEL')
            ->where('source_reference', $receiptId)
            ->orderByDesc('id')
            ->first();

        if (!$purchaseRequest) {
            try {
                $payload = [
                    ...$request->all(),
                    'origen' => 'FACTURADOR_LARAVEL',
                    'id_recibo' => $receiptId,
                ];
                $purchaseRequest = $this->service->createFromInvoiceWebhookPayload($payload);
            } catch (\Throwable $e) {
                return response()->json(['error' => 'No se pudo ubicar/crear la solicitud para adjuntar evidencias'], 400);
            }
        }

        $screenshots = $request->file('quoteScreenshots', []);
        if ($screenshots && !is_array($screenshots)) {
            $screenshots = [$screenshots];
        }
        $proof = $request->file('paymentProof');

        $created = 0;
        $proofAdded = false;

        if (is_array($screenshots)) {
            foreach ($screenshots as $file) {
                $stored = $this->service->storeUpload($file);
                RequestAttachment::query()->create([
                    'request_id' => $purchaseRequest->id,
                    'type' => 'QUOTE_SCREENSHOT',
                    ...$stored,
                    'uploaded_at' => now(),
                ]);
                $created++;
            }
        }

        if ($proof) {
            $stored = $this->service->storeUpload($proof);
            RequestAttachment::query()->create([
                'request_id' => $purchaseRequest->id,
                'type' => 'PAYMENT_PROOF',
                ...$stored,
                'uploaded_at' => now(),
            ]);
            $created++;
            $proofAdded = true;
        }

        if ($proofAdded && $purchaseRequest->status === 'Pendiente comprobante') {
            $purchaseRequest->status = 'Borrador';
            $purchaseRequest->save();

            RequestLog::query()->create([
                'request_id' => $purchaseRequest->id,
                'action' => 'payment_proof_attached',
                'from_status' => 'Pendiente comprobante',
                'to_status' => 'Borrador',
                'note' => 'Comprobante adjunto desde recibo',
                'created_at' => now(),
            ]);
        }

        return response()->json([
            'ok' => true,
            'request_id' => $purchaseRequest->id,
            'added' => $created,
        ]);
    }
}



