<?php

namespace Modules\Iorder\Entities;

use Modules\Core\Icrud\Entities\CrudModel;
use Modules\Isite\Relations\EmptyRelation;
use Modules\Notification\Traits\IsNotificable;

class Supply extends CrudModel
{
  use IsNotificable;
  protected $table = 'iorder__supplies';
  public $transformer = 'Modules\Iorder\Transformers\SupplyTransformer';
  public $repository = 'Modules\Iorder\Repositories\SupplyRepository';
  public $requestValidation = [
    'create' => 'Modules\Iorder\Http\Requests\CreateSupplyRequest',
    'update' => 'Modules\Iorder\Http\Requests\UpdateSupplyRequest',
  ];
  //Instance external/internal events to dispatch with extraData
  public $dispatchesEventsWithBindings = [
    //eg. ['path' => 'path/module/event', 'extraData' => [/*...optional*/]]
    'created' => [],
    'creating' => [],
    'updated' => [],
    'updating' => [],
    'deleting' => [],
    'deleted' => []
  ];
  protected $fillable = [
    'item_id',
    'status_id',
    'quantity',
    'price',
    'total',
    'supplier_id',
    'comment',
    'options'
  ];

  public function getStatusAttribute()
  {
    $status = new Status();
    return $status->show($this->status_id);
  }

  public function item()
  {
    return $this->belongsTo(Item::class);
  }

  public function supplier()
  {
    $driver = config('asgard.user.config.driver');

    return $this->belongsTo("Modules\\User\\Entities\\{$driver}\\User", 'supplier_id');
  }

  public function external()
  {
    if (is_module_enabled('Iexternal')) {
      return $this->hasOne(\Modules\Iexternal\Entities\External::class, 'entity_id', 'supplier_id')
                  ->where('entity_type', '=', "Modules\\User\\Entities\\Sentinel\\User");
    }
    return new EmptyRelation();
  }

  /**
   * Make Notificable Params | to Trait
   * @param $event (created|updated|deleted)
   */
  public function isNotificableParams($event)
  {
    $response = [];
    $userId = \Auth::id() ?? null;
    $source = "iorder";
    $supplier = $this->supplier;

    if(!isset($supplier)) return $response;

    $orderId = $this->item->order->id;

    //Validation Event Created
    if($event=="created"){
      $response[$event] = [
        "title" => trans("iorder::supplies.title.createdEvent"),
        "message" => trans("iorder::supplies.messages.createdEvent", ['userName' => $supplier->first_name, 'id' => $orderId]),
        "email" => [$supplier->email],
        "broadcast" => [$this->supplier_id],
        "userId" => $userId,
        "source" => $source,
        "link" => url('/iadmin/#/orders/supplies/index')
      ];
    }

    if($event=="updated") {
      if (!in_array($this->status_id, [Status::SUPPLY_ACCEPTED, Status::SUPPLY_REFUSED])) {
        $response[$event] = [
          "title" => trans("iorder::supplies.title.updatedEvent",  ['id' => $orderId]),
          "message" => trans("iorder::supplies.messages.updatedEvent", ['userName' => $supplier->first_name, 'id' => $orderId, 'status' => $this->status['title'] ?? '']),
          "email" => [$supplier->email],
          "broadcast" => [$this->supplier_id],
          "userId" => $userId,
          "source" => $source,
          "link" => url('/iadmin/#/orders/supplies/index')
        ];
      }
    }

    \Log::info('Notification::Supply|'. $event .'|IsNotificable: email: ' . $supplier->email . ' | broadcast: ' . $this->supplier_id);

    return $response;

  }
}
