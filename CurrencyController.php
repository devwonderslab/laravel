<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Yajra\Datatables\Facades\Datatables;

use App\Models\Currency;

class CurrencyController extends Controller
{
    /**
     * Show the currencies page.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        $this->authorize('access', Currency::class);
        return view('dashboard.currencies.index');
    }

    /**
     * Get all currencies.
     *
     * @return \Yajra\Datatables\Facades\Datatables
     */
    public function getCurrencies()
    {
        $this->authorize('access', Currency::class);
        $currencies = Currency::orderBy('created_at', 'desc')->get();

        return Datatables::of($currencies)
            ->addColumn('options', function($currency) {
                return
                    '<a href="' . route('dashboard.currencies.edit', ['id' => $currency->id]) .'" class="btn btn-warning btn-xs edit-currency" data-toggle="tooltip" title="Edit Currency">
                        <i class="fa fa-pencil" aria-hidden="true"></i>
                    </a>
                    <button class="btn btn-danger btn-xs delete-currency delete-grid-item" data-toggle="tooltip" title="Delete Currency" data-item-id="' . $currency->id . '" data-href="' . route('dashboard.currencies.delete', ['id' => $currency->id]) . '">
                        <i class="fa fa-trash-o" aria-hidden="true"></i>
                    </button>';
            })
            ->make(true);
    }

    /**
     * Create currency.
     */
    public function create()
    {
        $this->authorize('access', Currency::class);
        return view('dashboard.currencies.create');
    }

    /**
     * Store new currency.
     *
     * @param $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $this->authorize('access', Currency::class);
        // Data validation.
        $this->validate($request, [
            'title' => 'required|max:20'
        ]);

        // Save currency.
        $currency = new Currency();

        $currency->title = $request->get('title');

        $currency->save();

        Session::flash('flash', trans('messages.successfullyAdded'));

        return redirect()->route('dashboard.currencies.index');
    }

    /**
     * Edit currency.
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $this->authorize('access', Currency::class);
        $currency = Currency::findOrFail($id);

        return view('dashboard.currencies.edit', compact('currency'));
    }

    /**
     * Update currency.
     *
     * @param $id
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update($id, Request $request)
    {
        $this->authorize('access', Currency::class);
        // Data validation.
        $this->validate($request, [
            'title' => 'required|max:20'
        ]);

        // Update currency.
        $currency = Currency::findOrFail($id);

        $currency->title = $request->get('title');

        $currency->save();

        Session::flash('flash', trans('messages.successfullyUpdated'));

        return redirect()->route('dashboard.currencies.index');
    }

    /**
     * Delete currency.
     *
     * @param $id
     * @param $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete($id, Request $request)
    {
        $this->authorize('access', Currency::class);
        if($request->ajax()) {

            Currency::destroy($id);

            return response()->json([
                'success' => trans('messages.successfullyDeleted')
            ], 200);
        } else {
            return response()->json([
                'error' => trans('messages.badRequest')
            ], 400);
        }
    }
}
