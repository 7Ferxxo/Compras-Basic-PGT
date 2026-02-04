<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\PurchaseRequest;
use App\Models\RequestAttachment;
use App\Models\RequestLog;
use App\Models\Store;
use App\Services\Compras\PurchaseRequestService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PurchaseRequestsController extends Controller
{
    public function __construct(private readonly PurchaseRequestService $service)
    {
    }

    private function allowedStatuses(): array
    {
        return [
            'pending',
            'sent_to_supervisor',
            'completed',
            'Borrador',
            'Pendiente comprobante',
            'Enviada al Supervisor',
            'En proceso',
            'Compra realizada',
            'Completada',
        ];
    }

    private function normalizeStatus(?string $status): string
    {
        return match ($status) {
            'Borrador', 'Pendiente comprobante', 'Pendiente' => 'pending',
            'Enviada al Supervisor', 'En proceso' => 'sent_to_supervisor',
            'Compra realizada', 'Completada' => 'completed',
            'approved', 'Aprobada' => 'completed',
            'rejected', 'Rechazada', 'cancelled', 'Cancelada' => 'pending',
            default => $status ?: 'pending',
        };
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'sent_to_supervisor' => 'En proceso',
            'completed' => 'Completada',
            default => $status ?: 'Pendiente',
        };
    }

    private function errorResponse(string $message, int $status = 400, mixed $errors = null)
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'errors' => $errors ?: ['message' => [$message]],
        ], $status);
    }

    private function okResponse(mixed $data, int $status = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'errors' => null,
        ], $status);
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
                ->map(fn ($s) => $this->normalizeStatus($s))
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

        $rows = $rows->map(function ($row) {
            $row->status = $this->normalizeStatus($row->status ?? null);
            $row->status_label = $this->statusLabel($row->status);
            $row->payment_proof_url = $row->payment_proof_file
                ? Storage::disk('public')->url($row->payment_proof_file)
                : null;
            return $row;
        });

        return $this->okResponse([
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
            return $this->errorResponse('Solicitud no encontrada', 404);
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
                    'url' => Storage::disk('public')->url($a->stored_name),
                ];
            })
            ->values();

        $logs = RequestLog::query()
            ->where('request_id', (int) $id)
            ->orderBy('created_at')
            ->get();

        $row->status = $this->normalizeStatus($row->status ?? null);
        $row->status_label = $this->statusLabel($row->status);
        $hasAccountPassword = !empty($row->account_password_enc);
        unset($row->account_password_enc);

        return $this->okResponse([
            ...((array) $row),
            'hasAccountPassword' => $hasAccountPassword,
            'attachments' => $attachments,
            'logs' => $logs,
            'receipt_status' => [
                'sent' => !is_null($row->receipt_sent_at),
                'sent_at' => $row->receipt_sent_at,
                'error' => $row->receipt_send_error,
                'attempts' => $row->receipt_send_attempts ?? 0,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clientName' => ['required', 'string', 'max:120'],
            'clientCode' => ['required', 'string', 'max:50'],
            'contactChannel' => ['nullable', 'string', 'max:80'],
            'paymentMethod' => ['nullable', 'string', 'max:20', Rule::in(['Transferencia', 'Yappy', 'Efectivo', 'Tarjeta'])],
            'accountEmail' => ['nullable', 'string', 'max:255'],
            'accountPassword' => ['nullable', 'string', 'max:500'],
            'storeId' => ['nullable', 'integer', 'min:1'],
            'storeCustomName' => ['nullable', 'string', 'max:255'],
            'itemLink' => ['nullable', 'string', 'max:2000'],
            'itemOptions' => ['nullable', 'string', 'max:2000'],
            'itemQuantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'quotedTotal' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'quoteScreenshots' => ['required'],
            'quoteScreenshots.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'paymentProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Datos invalidos', 422, $validator->errors());
        }

        $validated = $validator->validated();

        $storeId = (int) ($validated['storeId'] ?? 0);
        $storeCustomName = $validated['storeCustomName'] ?? null;
        if (!$storeId) {
            $picked = $this->service->pickStoreFromItemLink($validated['itemLink'] ?? '');
            $storeId = (int) $picked['storeId'];
            $storeCustomName = $picked['storeCustomName'] ?? $storeCustomName;
        }

        $this->service->ensureDefaultStoresExist();

        if (!Store::query()->whereKey($storeId)->exists()) {
            return $this->errorResponse('storeId invalido', 422, ['storeId' => ['storeId invalido']]);
        }

        if ($storeId === 7 && trim((string) $storeCustomName) === '') {
            return $this->errorResponse('storeCustomName es requerido cuando la tienda es OTROS', 422, [
                'storeCustomName' => ['storeCustomName es requerido cuando la tienda es OTROS'],
            ]);
        }

        $screenshots = $request->file('quoteScreenshots', []);
        if ($screenshots && !is_array($screenshots)) {
            $screenshots = [$screenshots];
        }
        if (!is_array($screenshots) || count($screenshots) < 1) {
            return $this->errorResponse('quoteScreenshots es requerido (minimo 1)', 422, [
                'quoteScreenshots' => ['quoteScreenshots es requerido (minimo 1)'],
            ]);
        }

        $quotedTotal = (float) $validated['quotedTotal'];
        $itemQuantity = max(1, (int) ($validated['itemQuantity'] ?? 1));
        $charges = $this->service->computeCharges($storeId, $itemQuantity, $quotedTotal);

        $paymentProof = $request->file('paymentProof');
        $status = 'pending';
        $tempCode = 'PENDING-' . \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10));

        $passwordEnc = null;
        $accountPassword = trim((string) ($validated['accountPassword'] ?? ''));
        if ($accountPassword !== '') {
            $passwordEnc = Crypt::encryptString($accountPassword);
        }

        $itemLink = trim((string) ($validated['itemLink'] ?? ''));
        $purchaseRequest = PurchaseRequest::query()->create([
            'code' => $tempCode,
            'client_name' => $validated['clientName'],
            'client_code' => $validated['clientCode'],
            'contact_channel' => trim((string) ($validated['contactChannel'] ?? '')) ?: 'WEB',
            'payment_method' => trim((string) ($validated['paymentMethod'] ?? '')) ?: null,
            'account_email' => trim((string) ($validated['accountEmail'] ?? '')) ?: null,
            'account_password_enc' => $passwordEnc,
            'store_id' => $storeId,
            'store_custom_name' => $storeId === 7 ? ($storeCustomName ?: 'OTROS') : null,
            'item_link' => $itemLink,
            'item_options' => $validated['itemOptions'] ?? null,
            'item_quantity' => $itemQuantity,
            'quoted_total' => $quotedTotal,
            'residential_charge' => $charges['residentialCharge'],
            'american_card_charge' => $charges['americanCardCharge'],
            'notes' => $validated['notes'] ?? null,
            'status' => $status,
            'source_system' => 'WEB',
        ]);

        $code = $this->service->assignCode($purchaseRequest);

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'created',
            'from_status' => null,
            'to_status' => $status,
            'note' => 'Creacion',
            'actor_name' => auth()->user()?->name ?? $validated['clientName'] ?? 'system',
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


        $storeName = $purchaseRequest->store?->name ?? 'OTROS';
        if ($purchaseRequest->store_id === 7 && $purchaseRequest->store_custom_name) {
            $storeName = $storeName . ' - ' . $purchaseRequest->store_custom_name;
        }

        event(new \App\Events\PurchaseRequestCreated(
            $purchaseRequest,
            $charges,
            $storeName
        ));

        return $this->okResponse([
            'id' => $purchaseRequest->id,
            'code' => $code,
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'receipt_queued' => !empty($purchaseRequest->account_email),
        ], 201);
    }

    public function patchStatus(Request $request, string $id)
    {
        $allowed = $this->allowedStatuses();
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'max:50', Rule::in($allowed)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($validator->fails()) {
            return $this->errorResponse('Datos invalidos', 422, $validator->errors());
        }
        $validated = $validator->validated();

        $purchaseRequest = PurchaseRequest::query()->find((int) $id);
        if (!$purchaseRequest) {
            return $this->errorResponse('Solicitud no encontrada', 404);
        }

        $nextStatus = $this->normalizeStatus($validated['status']);

        if (in_array($nextStatus, ['completed'], true) && !$this->isComprasAuthorized($request)) {
            return $this->errorResponse('No autorizado para cambiar a estado de compras', 403);
        }

        if ($nextStatus === 'sent_to_supervisor') {
            $hasProof = RequestAttachment::query()
                ->where('request_id', $purchaseRequest->id)
                ->where('type', 'PAYMENT_PROOF')
                ->exists();
            if (!$hasProof) {
                return $this->errorResponse('No se puede enviar sin comprobante de pago', 400);
            }
        }

        $fromStatus = $this->normalizeStatus($purchaseRequest->status);
        if ($fromStatus !== $nextStatus && $nextStatus === 'sent_to_supervisor' && $fromStatus !== 'pending') {
            return $this->errorResponse('Solo se puede enviar al supervisor desde pendiente', 400);
        }
        if ($fromStatus !== $nextStatus && $nextStatus === 'completed' && $fromStatus !== 'sent_to_supervisor') {
            return $this->errorResponse('Solo se puede completar despues de En proceso', 400);
        }
        $purchaseRequest->status = $nextStatus;
        $purchaseRequest->save();

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'status_change',
            'from_status' => $fromStatus,
            'to_status' => $nextStatus,
            'note' => trim((string) ($validated['note'] ?? '')) ?: null,
            'created_at' => now(),
            'actor_name' => auth()->user()?->name ?? 'system',
        ]);

        return $this->okResponse([
            'ok' => true,
            'status' => $nextStatus,
            'status_label' => $this->statusLabel($nextStatus),
        ]);
    }

    public function sendToSupervisor(Request $request, string $id)
    {
        $purchaseRequest = PurchaseRequest::query()->find((int) $id);
        if (!$purchaseRequest) {
            return $this->errorResponse('Solicitud no encontrada', 404);
        }

        $note = trim((string) ($request->input('note') ?? '')) ?: null;

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
            return $this->errorResponse('No se puede enviar sin comprobante de pago', 400);
        }

        $fromStatus = $this->normalizeStatus($purchaseRequest->status);
        if ($fromStatus !== 'pending') {
            return $this->errorResponse('Solo se puede enviar al supervisor desde pendiente', 400);
        }

        $purchaseRequest->status = 'sent_to_supervisor';
        $purchaseRequest->sent_note = $note;
        $purchaseRequest->sent_at = now();
        $purchaseRequest->save();

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'send_to_supervisor',
            'from_status' => $fromStatus,
            'to_status' => 'sent_to_supervisor',
            'note' => $note,
            'created_at' => now(),
            'actor_name' => auth()->user()?->name ?? 'system',
        ]);

        return $this->okResponse([
            'ok' => true,
            'status' => 'sent_to_supervisor',
            'status_label' => $this->statusLabel('sent_to_supervisor'),
        ]);
    }

    public function uploadAttachment(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'max:30', Rule::in(['ORDER_DOC', 'PAYMENT_PROOF', 'QUOTE_SCREENSHOT'])],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        if ($validator->fails()) {
            return $this->errorResponse('Datos invalidos', 422, $validator->errors());
        }
        $validated = $validator->validated();

        $purchaseRequest = PurchaseRequest::query()->find((int) $id);
        if (!$purchaseRequest) {
            return $this->errorResponse('Solicitud no encontrada', 404);
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
            'from_status' => $this->normalizeStatus($purchaseRequest->status),
            'to_status' => $this->normalizeStatus($purchaseRequest->status),
            'note' => 'Adjunto: ' . $validated['type'],
            'created_at' => now(),
            'actor_name' => auth()->user()?->name ?? 'system',
        ]);

        return $this->okResponse([
            'ok' => true,
            'attachment_id' => $attachment->id,
        ]);
    }

    public function createFromInvoiceWebhook(Request $request)
    {
        $expectedToken = trim((string) config('services.facturacion.webhook_token', ''));
        if ($expectedToken !== '') {
            $got = (string) ($request->header('x-webhook-token') ?? '');
            if ($got === '' || !hash_equals($expectedToken, $got)) {
                return $this->errorResponse('Token de webhook invalido', 401);
            }
        }

        try {
            $created = $this->service->createFromInvoiceWebhookPayload($request->all());
            return $this->okResponse([
                'ok' => true,
                'id' => $created->id,
                'code' => $created->code,
                'status' => $this->normalizeStatus($created->status),
                'status_label' => $this->statusLabel($this->normalizeStatus($created->status)),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Log::error('Error creando solicitud desde webhook de recibo', ['error' => $e->getMessage()]);
            return $this->errorResponse('Error inesperado', 500);
        }
    }

    public function attachFromReceipt(Request $request, string $reciboId)
    {
        $validator = Validator::make($request->all(), [
            'quoteScreenshots' => ['nullable'],
            'quoteScreenshots.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'paymentProof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'cliente' => ['nullable', 'string', 'max:120'],
            'casillero' => ['nullable', 'string', 'max:50'],
            'metodo_pago' => ['nullable', 'string', 'max:20'],
            'descripcion_compra' => ['nullable', 'string', 'max:2000'],
            'monto_pagado' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'link_producto' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return $this->errorResponse('Datos invalidos', 422, $validator->errors());
        }

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
                return $this->errorResponse('No se pudo ubicar/crear la solicitud para adjuntar evidencias', 400);
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

        if ($proofAdded && $this->normalizeStatus($purchaseRequest->status) === 'pending') {
            $purchaseRequest->status = 'pending';
            $purchaseRequest->save();

            RequestLog::query()->create([
                'request_id' => $purchaseRequest->id,
                'action' => 'payment_proof_attached',
                'from_status' => 'pending',
                'to_status' => 'pending',
                'note' => 'Comprobante adjunto desde recibo',
                'created_at' => now(),
                'actor_name' => auth()->user()?->name ?? 'system',
            ]);
        }

        return $this->okResponse([
            'ok' => true,
            'request_id' => $purchaseRequest->id,
            'added' => $created,
        ]);
    }

    public function resendReceipt(Request $request, string $id)
    {
        $purchaseRequest = PurchaseRequest::query()->find((int) $id);
        if (!$purchaseRequest) {
            return $this->errorResponse('Solicitud no encontrada', 404);
        }

        $emailTo = trim((string) $purchaseRequest->account_email);
        if ($emailTo === '') {
            return $this->errorResponse('La solicitud no tiene email asociado', 400);
        }

        $charges = $this->service->computeCharges(
            $purchaseRequest->store_id,
            $purchaseRequest->item_quantity,
            (float) $purchaseRequest->quoted_total
        );

        $storeName = $purchaseRequest->store?->name ?? 'OTROS';
        if ($purchaseRequest->store_id === 7 && $purchaseRequest->store_custom_name) {
            $storeName = $storeName . ' - ' . $purchaseRequest->store_custom_name;
        }

        \App\Jobs\SendPurchaseRequestNotification::dispatch(
            $purchaseRequest->id,
            $charges,
            $storeName
        );

        RequestLog::query()->create([
            'request_id' => $purchaseRequest->id,
            'action' => 'receipt_resend_requested',
            'from_status' => $this->normalizeStatus($purchaseRequest->status),
            'to_status' => $this->normalizeStatus($purchaseRequest->status),
            'note' => 'Reenvío de comprobante solicitado manualmente',
            'actor_name' => auth()->user()?->name ?? 'system',
        ]);

        return $this->okResponse([
            'ok' => true,
            'message' => 'Comprobante en cola para reenvío',
            'email' => $emailTo,
        ]);
    }
}
