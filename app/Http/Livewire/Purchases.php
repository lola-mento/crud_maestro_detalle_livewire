<?php

namespace App\Http\Livewire;

use App\Product;
use App\Provider;
use App\Purchase;
use App\PurchaseDetail;
use Livewire\Component;

class Purchases extends Component
{

    public $provider_id,$type,$number_fact,$purchase_date,$tax=12,$picture; //array para la tabla de productos
    public $product_id,$quantity,$buy_price,$sell_price,$subtotal,$taxiva,$itemtotal,$total; //para la tabla de detalle producto
    public $selected_id, $search; //seleccionar y buscar
    public $providers, $provider, $products; //para los listados
    public $orderProducts = []; //array para el detalle de los productos
    public $action = 1;

    public function render()
    {
        $this->products = Product::all();
        $this->providers = Provider::all();
        if($this->selected_id>0)
        {
            $this->provider = Provider::find($this->selected_id);
            $this->provider_id = $this->provider->id;
            return view('livewire.purchases',[
                'products' => $this->products,
                'provider' => $this->provider_id,
                'orderProducts' => $this->orderProducts,
            ]);
        }
        else
        {
            return view('livewire.purchases',[
                'products' => $this->products,
                'providers' => $this->providers,
                'orderProducts' => $this->orderProducts
            ]);
        }
    }
    public function doAction($action)
    {
        $this->selected_id = $action;
    }
    public function resetInput()
    {
        $this->product_id = '';
        $this->quantity = null;
        $this->buy_price = null;
        $this->sell_price = null;
    }
    public function addProduct()
    {
        if($this->product_id == '' || $this->quantity == '' || $this->buy_price=='' || $this->sell_price=='')
        {
            $this->emit('msg-error','Ingrese todos los campos para agregar un producto a la lista');
        }
        else
        {
            $product = Product::find($this->product_id);
            $name = $product->name;
            $this->itemtotal = floatval($this->quantity) * floatval($this->buy_price);
            $this->subtotal = floatval($this->subtotal) + floatval($this->itemtotal);
            $this->taxiva = (floatval($this->subtotal)* floatval($this->tax))/100;
            $this->total = floatval($this->subtotal) + floatval($this->taxiva);

            $orderProducts = array
            (
                'product_id' => $this->product_id,
                'name' => $name,
                'quantity' => $this->quantity,
                'buy_price' => $this->buy_price,
                'sell_price' => $this->sell_price,
                'itemtotal' => $this->itemtotal,
            );
            $this->orderProducts[] = $orderProducts;
            $this->emit('msgok','Agregado con exito');
            $this->resetInput();
        }
    }
    public function removeItem($key)
    {
        $this->subtotal = $this->subtotal - $this->orderProducts[$key]['itemtotal'];
        $this->taxiva = (floatval($this->subtotal)* floatval($this->tax))/100;
        $this->total = floatval($this->subtotal) + floatval($this->taxiva);
        unset($this->orderProducts[$key]);
        $this->emit('msgok','Eliminado con exito');
    }
    public function storeOrder()
    {
        $this->validate
        ([
            'provider_id' => 'required',
            'type' => 'required',
            'number_fact' => 'required',
            'purchase_date' => 'required'
        ]);
        $order = Purchase::create
        ([
            'provider_id' => $this->provider_id,
            'user_id' => auth()->user()->id,
            'type'=> $this->type,
            'number_fact'=> $this->number_fact,
            'purchase_date'=> $this->purchase_date,
            'total'=> $this->total,
            'tax'=> $this->taxiva
        ]);

        foreach($this->orderProducts as $key => $product)
        {
            $results = array
            (
                "purchase_id" => $order->id,
                "product_id" => $product['product_id'],
                "quantity" => $product['quantity'],
                "buy_price" => $product['buy_price'],
                "sell_price" => $product['sell_price'],
                "created_at" => now(),
                "updated_at" => now()
            );
            PurchaseDetail::insert($results);
        }
        $this->emit('msgok','Compra creada con exito');
        foreach($this->orderProducts as $key => $product)
        {
            $item = Product::find($product['product_id']);
            $stock = $item->stock + $product['quantity'];
            $item->update([
                'stock' => $stock,
                'buy_price' => $product['buy_price'],
                'sell_price' => $product['sell_price']
            ]);
        }
        session()->flash('message','Se ha actualizado el stock de productos adquiridos');
        return redirect()->route('purchases.index');
    }

}
