<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Workshop;
use App\Appointment;


class TestController extends Controller
{
    /**
     * List down all the appointments for all workshops with ability to filter by each workshop
     */
    public function workshops(Request $request)
    {
        if ($request->has('name')) {
            $post=$request->only(['name']);
            $rules=[
                'name' => 'string',
            ];
            $messages=[
                'string'=>':attribute is string',
            ];
            $validator = Validator::make($post, $rules, $messages);
            if($validator->fails())
            {
                return response()->json(['code'=>400,'msg'=>$validator->errors()->first()]);
            }
        }
        /**
         * create Workshop object
         */
        $Workshop=Workshop::with('appointments');

        /**
         * Check whether the parameter exists
         */
        if($request->filled('name')){
            /**
             * Connection query parameters
             */
            $Workshop=$Workshop->where('name','like',"%".$post['name']."%");
        }
        /**
         * Query Workshop data
         */
        $Workshop=$Workshop->paginate(10);

        return response()->json(['code'=>200,'data'=>$Workshop,'msg' => 'success']);
    }

    /**
     * Schedule an appointment based on client's request
     */
    public function appointment(Request $request)
    {
        $post=$request->only(['car_id','workshop_id','start_time','end_time']);
        $rules=[
            'car_id' => ['required','numeric','exists:cars,id'],
            'workshop_id' => ['required','numeric','exists:workshops,id'],
            'start_time'=>['required','date_format:"H:i"'],
            'end_time'=>['required','date_format:"H:i"'],
        ];
        $validator = Validator::make($post, $rules);
        if($validator->fails())
        {
            return response()->json(['code'=>400,'msg'=>$validator->errors()->first()]);
        }
        /**
         * create Appointment object
         */
        $Appointment=new Appointment();
        /**
         * Check whether there is an appointment
         */
        if($Appointment->whereTime("start_time",">=",$post['start_time'])->whereTime('end_time',"<=",$post['end_time'])->exists())
        {
            return response()->json(['code'=>400,'msg'=>"I'm sorry, we have an appointment in this period"]);
        }

        $Appointment->car_id=$post['car_id'];
        $Appointment->workshop_id=$post['workshop_id'];
        $Appointment->start_time=$post['start_time'];
        $Appointment->end_time=$post['end_time'];
        /**
         * insert data
         */
        if($Appointment->save())
        {
            return response()->json(['code'=>200,'data'=>[],'msg' => 'success']);
        }
    }

    /**
     * Recommend the workshops based on the availability and the locations
     */
    public function workshops_list(Request $request)
    {
        $post=$request->only(['start_time','end_time','latitude','longitude']);
        $rules=[
            'start_time' => ['required','date_format:"H:i"'],
            'end_time' => ['required','date_format:"H:i"'],
            'latitude' => ['required'],
            'longitude' => ['required']
        ];
        $validator = Validator::make($post, $rules);
        if($validator->fails())
        {
            return response()->json(['code'=>400,'msg'=>$validator->errors()->first()]);
        }

        $Workshop=Workshop::selectRaw('id, name, phone,latitude, longitude, opening_time,closing_time,round((
            6370.996 * acos (  
              cos ( radians( ? ) )  
              * cos( radians( latitude ) )  
              * cos( radians( longitude ) - radians( ? ) )  
              + sin ( radians( ? ) )  
              * sin( radians( latitude ) )  
            )  
            ),1) AS distance', [$post['latitude'], $post['longitude'], $post['latitude']])
        ->with(['appointments'=>function($query) use($post){
            $query->whereTime('start_time',">=", $post['start_time'])->whereTime('end_time',"<=", $post['end_time']);
        }])
        ->orderBy('distance')
        ->get();
        return response()->json(['code'=>200,'data'=>$Workshop,'msg' => 'success']);
    }
}
