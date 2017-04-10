<?php

namespace App\Http\Controllers\Dashboard;

use App\Helpers\PriceHelper;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Http\Controllers\Controller;
use App\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Yajra\Datatables\Facades\Datatables;

use App\Models\Order;

class OrderController extends Controller
{
    /**
     * Show roles page.
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $this->authorize('access', [Order::class, $restaurant]);
        return view('dashboard.orders.index', compact('restaurant'));
    }

    /**
     * Get all active orders.
     *
     * @return \Yajra\Datatables\Facades\Datatables;
     */
    public function getOrders($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $this->authorize('access', [Order::class, $restaurant]);
        $orders = Order::where(['restaurant_id' => $id])
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.status', '<>', OrderItem::STATUS_CANCELLED)
            ->groupBy('order_items.order_id')
            ->with('items')
            ->select(
                'orders.id',
                'orders.restaurant_id',
                'orders.table_number',
                'orders.created_at',
                'orders.status',
                \DB::raw('SUM(order_items.price * order_items.amount) as total')
            )
            ->whereIn('orders.status', [Order::STATUS_PENDING, Order::STATUS_PREPARING, Order::STATUS_SERVED])
            ->orderBy('orders.status', 'desc')
            ->orderBy('orders.updated_at', 'desc')
            ->get();

        return Datatables::of($orders)
            ->setRowClass(function ($order) {
                switch ($order->status) {
                    case Order::STATUS_PENDING:
                        return 'warning';
                        break;
                    case Order::STATUS_PREPARING:
                        return 'success';
                        break;
                    case Order::STATUS_SERVED:
                        return 'info';
                        break;
                }
            })
            ->addColumn('total', function ($order) use ($restaurant) {
                return PriceHelper::fixPrice($order->total, $restaurant);
            })
            ->addColumn('status', function ($order) {
                return $order->getStatusText();
            })
            ->addColumn('options', function ($order) {
                $result =
                    '<a href="' . route('dashboard.orderItems.index', ['id' => $order->id]) . '" class="btn btn-default btn-xs">
                        <i class="fa fa-cutlery" aria-hidden="true"></i> ' . trans('messages.items') . '
                    </a>';
                if (Auth::user()->can('update', $order)) {
                    switch ($order->status) {
                        case Order::STATUS_PENDING:
                            $result .=
                                '<button class="btn btn-success btn-xs update-orderStatus update-grid-item" data-item-id="' . $order->id . '" data-href="' . route('dashboard.orders.prepare',
                                    ['id' => $order->id]) . '">
                                <i class="fa fa-hourglass-start" aria-hidden="true"></i> ' . trans('messages.prepare') . '
                            </button>
                            <button class="btn btn-danger btn-xs update-orderStatus update-grid-item" data-item-id="' . $order->id . '" data-href="' . route('dashboard.orders.cancel',
                                    ['id' => $order->id]) . '">
                                <i class="fa fa-times" aria-hidden="true"></i> ' . trans('messages.cancel') . '
                            </button>';
                            break;
                        case Order::STATUS_PREPARING:
                            if (Auth::user()->can('prepare-orders', [AuthServiceProvider::class, Auth::user()])) {
                                $result .=
                                    '<button class="btn btn-info btn-xs update-orderStatus update-grid-item" data-item-id="' . $order->id . '" data-href="' . route('dashboard.orders.serve',
                                        ['id' => $order->id]) . '">
                                    <i class="fa fa-shopping-bag" aria-hidden="true"></i> ' . trans('messages.serve') . '
                                </button>';
                            }
                            break;
                        case Order::STATUS_SERVED:
                            if (Auth::user()->can('prepare-orders', [AuthServiceProvider::class, Auth::user()])) {
                                $result .=
                                    '<button class="btn btn-warning btn-xs update-orderStatus update-grid-item" data-item-id="' . $order->id . '" data-href="' . route('dashboard.orders.pay',
                                        ['id' => $order->id]) . '">
                                    <i class="fa fa-shopping-bag" aria-hidden="true"></i> ' . trans('messages.pay') . '
                                </button>';
                                break;
                            }
                    }
                }
                if (Auth::user()->can('prepare-orders', [AuthServiceProvider::class, Auth::user()])) {
                    $result .=
                        '<a href="' . route('dashboard.orders.invoice', ['id' => $order->id]) . '" class="btn btn-default btn-xs">
                        <i class="fa fa-print" aria-hidden="true"></i> ' . trans('messages.printInvoice') . '
                    </a>';
                }
                return $result;
            })
            ->make(true);
    }


    /**
     * Get all closed orders.
     *
     * @return \Yajra\Datatables\Facades\Datatables;
     */
    public function getClosedOrders($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $this->authorize('access', [Order::class, $restaurant]);
        $orders = Order::where(['restaurant_id' => $id])
            ->leftJoin('order_items', function ($join) {
                $join->on('order_items.order_id', '=', 'orders.id')
                    ->where('order_items.status', '<>', OrderItem::STATUS_CANCELLED);
            })
            ->groupBy('orders.id')
            ->with('items')
            ->select(
                'orders.id',
                'orders.table_number',
                'orders.created_at',
                'orders.status',
                \DB::raw('SUM(order_items.price * order_items.amount) as total')
            )
            ->whereIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_PAID])
            ->orderBy('orders.status', 'desc')
            ->orderBy('orders.updated_at', 'desc')
            ->get();

        return Datatables::of($orders)
            ->addColumn('total', function ($order) use ($restaurant) {
                return PriceHelper::fixPrice($order->total, $restaurant);
            })
            ->addColumn('status', function ($order) {
                return $order->getStatusText();
            })
            ->addColumn('options', function ($order) {
                if ($order->status === Order::STATUS_PAID) {
                    return
                        '<a href="' . route('dashboard.orderItems.index', ['id' => $order->id]) . '" class="btn btn-info btn-xs">
                            <i class="fa fa-cutlery" aria-hidden="true"></i>
                        </a>';
                }
                return '';
            })
            ->make(true);
    }

    /**
     * Order Invoice.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function invoice($id)
    {
        $order = Order::whereIn('orders.status',
            [Order::STATUS_PENDING, Order::STATUS_PREPARING, Order::STATUS_SERVED, Order::STATUS_PAID])
            ->where('orders.id', $id)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.status', '<>', OrderItem::STATUS_CANCELLED)
            ->groupBy('order_items.order_id')
            ->select(
                'orders.id',
                'orders.restaurant_id',
                'orders.comment',
                'orders.table_number',
                'orders.created_at',
                'orders.status',
                \DB::raw('SUM(order_items.price * order_items.amount) as total')
            )
            ->firstOrFail();
        $this->authorize('items', $order);

        $orderItems = OrderItem::where(['order_id' => $id])
            ->join('item_translations', 'item_translations.item_id', '=', 'order_items.item_id')
            ->where('item_translations.locale', LaravelLocalization::getCurrentLocale())
            ->where('order_items.status', '<>', OrderItem::STATUS_CANCELLED)
            ->select(
                'order_items.id',
                'item_translations.name as meal_title',
                'order_items.price as cost',
                'order_items.amount as quantity',
                \DB::raw('order_items.price * order_items.amount as sub_total'),
                'order_items.status',
                'order_items.comment'
            )
            ->orderBy('order_items.status', 'desc')
            ->orderBy('order_items.updated_at', 'asc')
            ->get();

        $restaurant = Restaurant::where('id', $order->restaurant->id)->firstOrFail();
        $order->total = PriceHelper::fixPrice($order->total, $restaurant);
        foreach ($orderItems as $orderItem) {
            $orderItem->cost = PriceHelper::fixPrice($orderItem->cost, $restaurant);
            $orderItem->sub_total = PriceHelper::fixPrice($orderItem->sub_total, $restaurant);
        }

        return view('dashboard.orders.invoice', compact('order', 'orderItems'));
    }

    /**
     * Latest orders update.
     *
     * @param $restaurant_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function latestUpdate($restaurant_id, Request $request)
    {
        $orderExists = Order::where('orders.restaurant_id', $restaurant_id)
            ->where('updated_at', '>', date('Y-m-d H:i:s', $request->get('latestUpdate')))
            ->exists();
        $pendingOrdersAmount = null;
        $newPendingOrder = false;
        $newPreparingOrder = false;
        if ($orderExists) {
            $newPendingOrder = Order::where('orders.restaurant_id', $restaurant_id)
                ->where('updated_at', '>', date('Y-m-d H:i:s', $request->get('latestUpdate')))
                ->where('status', Order::STATUS_PENDING)
                ->exists();
            $newPreparingOrder = Order::where('orders.restaurant_id', $restaurant_id)
                ->where('updated_at', '>', date('Y-m-d H:i:s', $request->get('latestUpdate')))
                ->where('status', Order::STATUS_PREPARING)
                ->exists();
            $pendingOrdersAmount = Order::getPendingOrdersCount($restaurant_id);
        }
        return response()->json([
            'time' => time(),
            'orderUpdatesExist' => $orderExists,
            'pendingOrdersAmount' => $pendingOrdersAmount,
            'newPendingOrder' => $newPendingOrder,
            'newPreparingOrder' => $newPreparingOrder,
        ], 200);
    }

    /**
     * Prepare order.
     *
     * @param $id
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function prepare($id, Request $request)
    {
        return $this->updateOrderItemStatus($id, Order::STATUS_PREPARING, $request);
    }

    /**
     * Serve order.
     *
     * @param $id
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function serve($id, Request $request)
    {
        return $this->updateOrderItemStatus($id, Order::STATUS_SERVED, $request);
    }

    /**
     * Pay order.
     *
     * @param $id
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pay($id, Request $request)
    {
        return $this->updateOrderItemStatus($id, Order::STATUS_PAID, $request);
    }

    /**
     * Cancel order.
     *
     * @param $id
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancel($id, Request $request)
    {
        return $this->updateOrderItemStatus($id, Order::STATUS_CANCELLED, $request);
    }

    protected function updateOrderItemStatus($id, $status, $request)
    {
        if ($request->ajax()) {
            $order = Order::findOrFail($id);

            $this->authorize('update', $order);

            $order->status = $status;
            $order->save();

            switch ($status) {
                case Order::STATUS_PREPARING:
                    OrderItem::where('order_id', $order->id)
                        ->where('status', OrderItem::STATUS_PENDING)
                        ->update(['status' => OrderItem::STATUS_PREPARING]);
                    break;
                case Order::STATUS_SERVED:
                case Order::STATUS_PAID:
                    OrderItem::where('order_id', $order->id)
                        ->whereIn('status', [OrderItem::STATUS_PENDING, OrderItem::STATUS_PREPARING])
                        ->update(['status' => OrderItem::STATUS_SERVED]);
                    break;
                case Order::STATUS_CANCELLED:
                    OrderItem::where('order_id', $order->id)
                        ->where('status', '<>', OrderItem::STATUS_CANCELLED)
                        ->update(['status' => OrderItem::STATUS_CANCELLED]);
                    break;
            }

            return response()->json([
                'success' => trans('messages.successfullyUpdated')
            ], 200);
        } else {
            return response()->json([
                'error' => trans('messages.badRequest')
            ], 400);
        }
    }

    public function addItem($id, Request $request)
    {
        // Data validation.
        $this->validate($request, [
            'item_id' => 'required|integer|min:1',
            'quantity' => 'integer|min:1',
            'comment' => 'string',
        ]);

        // Save Order item.
        $orderItem = new OrderItem();

        $orderItem->order_id = $id;
        $orderItem->item_id = $request->get('item_id');

        $menuItem = Item::findOrFail($orderItem['item_id']);

        $orderItem->comment = $request->has('comment') ? $request->get('comment') : '';
        $orderItem->status = OrderItem::STATUS_PENDING;
        $orderItem->amount = $request->has('quantity') ? $request->get('quantity') : 1;
        // Copying menu item price to the order item.
        $orderItem->price = $menuItem->price;

        if (!$orderItem->save()) {
            abort('409', 'Failed to save order item');
        }

        Session::flash('flash', trans('messages.successfullyAdded'));

        return redirect()->route('dashboard.orderItems.index', ['id' => $id]);
    }
}
