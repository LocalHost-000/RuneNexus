<?php
 use Illuminate\Database\Eloquent\Model as Model;
 use Rakit\Validation\Validator;

 class Videos extends Model {

     public $timestamps    = false;
     public $incrementing  = true;
     protected $primaryKey = 'id';

     protected $table = "videos";

     protected $fillable = [
         'title',
         'category',
         'author_id',
         'embed',
         'meta_description',
         'date_posted'
     ];

     public static function validate($validate){
        $validator = new Validator;

        $validation = $validator->validate($validate, [
            'title'     => 'required',
            'category'  => 'required|min:3|max:15',
            'embed'   => 'required|min:50',
        ]);

        return $validation;
   }

} 