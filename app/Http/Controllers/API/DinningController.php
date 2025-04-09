<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\DB;

use App\Models\TableDetail;
use App\Models\ResidentDetail;
use App\Models\RoomDetail;
use Illuminate\Http\Request;
use App\Models\OrderDetail;
use App\Models\ItemDetail;
use App\Models\CategoryDetail;
use Carbon\Carbon;

class DinningController extends Controller
{

    public function getTableList()
    {
        $table_data = TableDetail::all();
        $table_array = array();
        foreach(count($table_data) > 0 ? $table_data : array() as $t){
           $residence_data = $t->residentData;
            array_push($table_array, array("id" => $t->table_id, "name" => $t->table_name, "resident_id" => ($residence_data ? $residence_data->resident_id : ""), "resident_name" => ($residence_data ? $residence_data->resident_name : ""),"resident_group_id" => ($residence_data ? $residence_data->resident_group_id : ""),"resident_group_name" => "Group 1"));
        }
        //dd($table_data);
        return $this->sendResultJSON('1', '', array('tables' => $table_array));
    }
    
    public function getResidentList()
    {
        $residents = ResidentDetail::all();
        $resident_array = array();
        foreach (count($residents) > 0 ? $residents : array() as $r) {
            array_push($resident_array, array("id" => $r->resident_id, "name" => $r->resident_name, "room_no" => $r->room_no, "group_id" => $r->resident_group_id, "group_name" => "Group 1", "table_id" => $r->table_id, "table_name" => ($r->tableData ? $r->tableData->table_name : ""), "basic_info" => $r->additional_info, "diet" => $r->diet_type, "allergy_info" => $r->allergy_info));
        }
        return $this->sendResultJSON('1', '', array('residents' => $resident_array));
    }
    
     public function getRoomList()
    {
        $rooms = RoomDetail::all();
        $rooms_array = array();
        foreach (count($rooms) > 0 ? $rooms : array() as $r) {
            array_push($rooms_array, array("id" => $r->room_id, "name" => $r->room_name));
        }
        return $this->sendResultJSON('1', '', array('rooms' => $rooms_array));
    }
    
     public  function getOrderList(Request $request){
        $room_id = intval($request->input('room_id'));
        $date = $request->input('date');
        $type = intval($request->input('type'));
        $order_details = array();
        $cat_array = array();

        $get_all_categories = CategoryDetail::where("type", $type)->get();
        foreach (count($get_all_categories) > 0 ? $get_all_categories : array() as $c) {
            array_push($cat_array, $c->id);
            $order_details[$c->id] = array("cat_id" => $c->id, "cat_name" => $c->cat_name, "items" => array());
        }
        if ($room_id != 0 && $date != "" && $type != "") {
            $order_data = OrderDetail::join("item_details", "order_details.item_id", "=", "item_details.id")->selectRaw("order_details.*,item_details.item_name,item_details.cat_id")->where("order_details.room_id", $room_id)->where("order_details.date", $date)->whereIn("item_details.cat_id", $cat_array)->get();
            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                array_push($order_details[$o->cat_id]["items"], array("item_id" => $o->item_id, "item_name" => $o->item_name, "item_image" => "http://itask.intelligrp.com/uploads/pexels-ella-olsson-1640777.jpg", "qty" => intval($o->quantity), "comment" => $o->comment, "order_id" => $o->id));
            }
        }
        return $this->sendResultJSON('1', '', array('data' => array_values($order_details)));
    }
    
     public function getItemList(Request $request)
    {
        $cat_id = $request->input('cat_id');
        $date = $request->input('date');
        if ($cat_id != "" && $date != "") {
           $date_query = "";
            if($date == "all"){
                $date_query = "(day = 'all')";
            }else{
                $date_query = "(day = '" . strtolower(Carbon::parse($date)->format("l")) . "' OR day = 'all')";
            }
          
            $item_details = ItemDetail::where("cat_id", $cat_id)->whereRaw($date_query)->get();
            $item_data = array();
            foreach (count($item_details) > 0 ? $item_details : array() as $i) {
                array_push($item_data, array("item_id" => $i->id, "item_name" => $i->item_name,"item_image" => "http://itask.intelligrp.com/uploads/pexels-ella-olsson-1640777.jpg", "qty" => 0, "comment" => "", "order_id" => 0));
            }
            return $this->sendResultJSON('1', '', array('items' => $item_data));
        }
    }
    
     public function updateOrder(Request $request)
    {
        $cat_id = $request->input('cat_id');
        $room_id = $request->input('room_id');
        $date = $request->input('date');
        if ($cat_id != "" && $room_id != "" && $date != "") {
            if ($request->input('orders_to_remove') && $request->input('orders_to_remove') != "") {
                $to_delete_order = $request->input('orders_to_remove');
                OrderDetail::whereIn("id", explode(",", $to_delete_order))->delete();
            }
              
            if ($request->input('orders_to_change') && $request->input('orders_to_change') != "") {
                $new_data = json_decode($request->input('orders_to_change'));
                foreach (count($new_data) > 0 ? $new_data : array() as $n) {
                    $n->id = intval($n->id);
                    if ($n->id == 0) {
                        $order = new OrderDetail();
                        $order->room_id = $room_id;
                        $order->date = $date;
                        $order->item_id = $n->item_id;
                        $order->quantity = $n->quantity;
                        $order->comment = $n->comment;
                        $order->status = 0;
                        $order->save();
                    } else {
                        OrderDetail::where("id", $n->id)->update(['quantity' => $n->quantity, 'comment' => $n->comment]);
                    }
                }
            }
            $result = array("cat_id" => "","cat_name" => "", "items" => array());
            $category_details = CategoryDetail::select("cat_name")->where("id", $cat_id)->first();
            if ($category_details) {
                $result["cat_id"] = $cat_id;
                $result["cat_name"] = $category_details->cat_name;
            }
            $order_data = OrderDetail::join("item_details", "order_details.item_id", "=", "item_details.id")->selectRaw("order_details.*,item_details.item_name,item_details.cat_id")->where("order_details.room_id", $room_id)->where("order_details.date", $date)->where("item_details.cat_id", $cat_id)->get();
            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                array_push($result["items"], array("item_id" => $o->item_id, "item_name" => $o->item_name,"item_image" => "http://itask.intelligrp.com/uploads/pexels-ella-olsson-1640777.jpg", "qty" => intval($o->quantity), "comment" => $o->comment, "order_id" => $o->id));
            }
            return $this->sendResultJSON('1', '', array('items' => $result));
        }
    }
    
     public function getCategoryWiseData(Request $request)
    {
        $date = $request->input('date');
        $type = intval($request->input('type'));
        $order_details = array();
        $cat_array = array();
        if ($date != "" && $type != "") {
            $get_all_categories = CategoryDetail::where("type", $type)->get();
            foreach (count($get_all_categories) > 0 ? $get_all_categories : array() as $c) {
                $cat_array[$c->id] = $c->cat_name;
            }
            $order_data = OrderDetail::join("item_details", "order_details.item_id", "=", "item_details.id")->selectRaw("order_details.*,item_details.item_name,item_details.cat_id")->where("order_details.date", $date)->whereIn("item_details.cat_id", array_keys($cat_array))->whereRaw("item_details.deleted_at is null")->get();
           
            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                $o->cat_id = intval($o->cat_id);
                if (!isset($order_details[$o->cat_id])) {
                    $order_details[$o->cat_id] = array("cat_id" => $o->cat_id, "cat_name" => $cat_array[$o->cat_id], "items" => array());
                }
                 if (isset($order_details[$o->cat_id])) {
                    if (!isset($order_details[$o->cat_id]["items"][$o->item_id])) {
                        $order_details[$o->cat_id]["items"][$o->item_id] = array("item_id" => $o->item_id, "item_name" => $o->item_name, "item_image" => "http://itask.intelligrp.com/uploads/pexels-ella-olsson-1640777.jpg", "qty" => intval($o->quantity));
                    } else {
                        $order_details[$o->cat_id]["items"][$o->item_id]["qty"] += intval($o->quantity);
                    }
                }
            }
            $result = array();
            foreach($order_details as $od){
                $od["items"] = array_values($od["items"]);
                array_push($result,$od);
            }
           
            return $this->sendResultJSON('1', '', array('data' => $result));
        }
    }
    
    public function getRoomData(Request $request)
    {
        $date = $request->input('date');
        $item_id = intval($request->input('item_id'));
        $order_details = array();
        $room_array = array();
        if ($date != "" && $item_id != "") {
            $rooms_data = RoomDetail::all();
            foreach ($rooms_data as $r) {
                $room_array[$r->room_id] = $r->room_name;
            }
           
            $order_data = OrderDetail::where("date", $date)->where("item_id", $item_id)->get();
          
            foreach (count($order_data) > 0 ? $order_data : array() as $o) {
                $order_details[$o->room_id] = array("room_id" => $o->room_id, "room_name" => $room_array[$o->room_id]);
            }
            return $this->sendResultJSON('1', '', array('rooms' => array_values($order_details)));
        }
    }
}
