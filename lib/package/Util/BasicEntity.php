<?php
namespace Package\Util;

use App\Model\Gallery;
use Exception;
use Illuminate\Support\Facades\File;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BasicEntity
{
    protected  $entity;

    protected  $id;

    protected $error;

    /**
     * @param array $attributes
     * @return bool
     */
    public function CreateOrUpdate(array $attributes)
    {
        DB::beginTransaction();
        try {
            $data = [];
            foreach ($this->fillable as $key => $value) {
                if (isset($attributes[$value])) {
                    $data[$value] = $attributes[$value];
                }
            }

            if (isset($data[$this->primaryKey]) == false) {
                $data['created_at'] = isset($data['created_at']) ? $data['created_at'] : now();
                $result = DB::table($this->table)->updateOrInsert($data);

                if (!$result) {
                    DB::rollBack();

                    return false;
                }

                DB::commit();
            } else {
                $data['updated_at'] = isset($data['updated_at']) ? $data['updated_at'] : now();
                $this->id = $data[$this->primaryKey];
                $flag = DB::table($this->table)
                    ->where($this->primaryKey, $this->id)
                    ->update($data);
                if (!$flag) {
                    DB::rollBack();

                    return false;
                }

                DB::commit();

            }
            return true;
        } catch (Exception $e) {
            DB::rollBack();

            $this->error = $e->getMessage();

            dd( $this->error);
            $log = now() .' | lỗi record | ' .  $e->getMessage();

            Log::channel('production')->error($log);

            return false;
        }
    }

    /**
     * @param array $arrId
     * @param array $attributes
     * @return bool
     */
    public function CreateOrUpdateMutilRow(array $arrId,array $attributes)
    {
        DB::beginTransaction();

        try
        {
            if (empty($arrId) == true) {
                foreach ($attributes as $key=>$value)
                {
                    $value['created_at'] =  now();
                    $data['insert_user_id'] =  '1';
                    $attributes[$key] = $value;
                }
                DB::table($this->table)->insert($attributes);

                DB::commit();

                return true;
            } else if(empty($table) == false && empty($arrId) == false)
                {
                    foreach ($attributes as $key=>$value)
                    {
                        $value['updated_at'] =  now();
                        $data['update_user_id'] =  '1';
                        $attributes[$key] = $value;
                    }
                    foreach ($arrId as $index=>$nodeValue)
                    {
                        $query = DB::table($this->table)->where($this->primaryKey,$nodeValue)->update($attributes[$index]);
                        if($query == false)
                        {
                            DB::rollBack();
                            return false;
                        }
                    }

                    DB::commit();

                    return true;
            }
            else
            {
                DB::rollBack();

                $this->error = 'Can run query update or insert';
                return false;
            }
        } catch (Exception $e) {
            DB::rollBack();

            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @param array $attributes
     * @return bool
     */
    public function CreateMutilRow(array $attributes)
    {
        DB::beginTransaction();

        try
        {
            if (count($attributes) > 0) {
                foreach ($attributes as $key => $value) {
                    $attributes[$key] = $value;
                }

                DB::table($this->table)->insert($attributes);

                DB::commit();

                return true;
            } else {
                DB::rollBack();

                $this->error = 'Can find id insert row';
                return false;
            }
        }
        catch (Exception $e)
        {
            DB::rollBack();

            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @param array $arrId
     * @param array $attributes
     * @return bool
     */
    public function UpdateMutilRow(array $arrId,array $attributes)
    {
        DB::beginTransaction();

        try
        {
            if (empty($arrId) == false) {
                foreach ($attributes as $key=>$value)
                {
                    $value['updated_at'] =  now();
                    $data['update_user_id'] =  '1';
                    $attributes[$key] = $value;
                }
                foreach ($arrId as $index=>$nodeValue)
                {
                    $query = DB::table($this->table)->where($this->primaryKey,$nodeValue)->update($attributes[$index]);
                    if($query == false)
                    {
                        DB::rollBack();

                        return false;
                    }
                }

                DB::commit();

                return true;
            } else {
                DB::rollBack();

                $this->error = 'name table or id not found for update';
                return false;
            }
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @param array $arrID
     * @return bool
     */

    public function DeleteMutilRow(array $arrID)
    {
        DB::beginTransaction();
        try
        {
            if (empty($arrID) == false) {
                $path = storage_path('logs/package.delete');
                $totalLine = count(file($path));
                $content = "\r\n #". $totalLine . ':delete row from '. $this->primaryKey .' /id:' .implode(",", $arrID) .' from table /tb:' .$this->table .' at time /t:' . now();
                $query = DB::table($this->table)
                    ->where('is_delete','=',1)
                    ->whereIn($this->primaryKey, $arrID)
                    ->delete();

                if(!$query)
                {
                    DB::rollBack();
                    return false;

                }

                DB::commit();

                $this->WriteLog($path,$content);

                return true;
            } else {
                $this->error = 'id of table '. $this->table .'not found for delete';
                return false;
            }
        }
        catch (Exception $e)
        {
            DB::rollBack();

            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @param null $pagin
     * @param null $lang
     * @return bool|\Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function findAll($pagin = null)
    {
        try
        {
            $table = $this->table;

            if(empty($pagin) == false)
            {
                $result = DB::table($table)
                    ->where("{$table}.is_active",'=',1)
                    ->where("{$table}.is_delete",'=',0)
                    ->paginate($pagin);
            }
            else
            {
                $result = DB::table($table)
                    ->where("{$table}.is_active",'=',1)
                    ->where("{$table}.is_delete",'=',0)
                    ->get();
            }

            return $result;
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @return bool|\Illuminate\Support\Collection
     */
    public function findAllTrash($pagin = null)
    {
        try
        {
            if(empty($lang) == true)
            {
                $lang = App::getLocale();
            }
            if(empty($pagin) == false)
            {
                $result = DB::table($this->table)
                    ->where('is_delete','=',1)
                    ->paginate($pagin);
            }
            else
            {
                $result = DB::table($this->table)
                    ->where('is_delete','=',1)
                    ->get();
            }
            return $result;
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }
    /**
     * @param $id: is id need find
     * @return bool|Model|\Illuminate\Database\Query\Builder|null|object
     */
    public function findOneById($id)
    {
        try
        {
            if (empty($id) == false ) {
                $result = DB::table($this->table)
                    ->where($this->primaryKey,$id)
                    ->first();
                return $result;
            } else {
                $this->error = 'Id can not null!';
                return false;
            }
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @param $table: name table
     * @param $keyFiled: key condition field
     * @param array $Attributes: value condition
     * @return bool|\Illuminate\Support\Collection
     */
    public function findMany($keyFiled, array $Attributes)
    {
        try
        {
            if ( empty($Attributes) == false && empty($keyFiled) == false ) {

                $result = DB::table($this->table)
                    ->whereIn($keyFiled,$Attributes)
                    ->get();
                return $result;
            } else {
                $this->error = 'field or attributes can not null!';
                return false;
            }
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }
    public function TrashOrRecover($id, $trash = true)
    {
        try
        {
            if ( empty($id) == false ) {
                if($trash == true)
                {
                    $data['is_delete'] = '1';
                }
                else
                {
                    $data['is_delete'] = '0';
                }
                $data['updated_at'] = now();
                $flag = DB::table($this->table)
                    ->where($this->primaryKey, $id)
                    ->update($data);
                if($flag == true)
                {
                    return true;
                }
                return false;

            } else {
                $this->error = 'id or table can not null!';
                return false;
            }
        }
        catch (Exception $e)
        {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function uploadAndSaveFile($files, $data = null, $author = "system")
    {
        try {
            DB::beginTransaction();

            $gallery_data = [];

            $title = isset($data->title) ? $data->title : null;

            if(isset($files) && count($files) > 0) {
                foreach ($files as $file) {
                    $filename = null;
                    if (isset($title)) {
                        $filename = str_replace(' ', '-', $title);
                    }

                    $files_result = Helper::UploadResource($file, $filename);

                    if ($files_result === false) {
                        Db::rollBack();

                        $this->error = 'Can not upload this file!';

                        return false;
                    }

                    if (!isset($title)) {
                        $filename = isset($files_result['file_name']) ? now()->timestamp . '_' . $files_result['file_name'] : now()->timestamp;
                    }

                    $gallery_data = [
                        'file_path' => isset($files_result['path']) ? $files_result['path'] : "",
                        'file_thumb' => isset($files_result['thumb']) ? $files_result['thumb'] : "",
                        'file_title'=> $filename,
                        'file_alt'=> isset($data->file_alt) ? $data->file_alt : "",
                        'file_description'=> isset($data->file_description) ? $data->file_description : "",
                        'file_author'=> $author,
                        'file_size'=> isset($files_result['size']) ? $files_result['size'] : 0,
                        'file_type'=> isset($files_result['type']) ? $files_result['type'] : "other",
                    ];

                    $save_result = Gallery::updateOrCreate($gallery_data);

                    if (!$save_result) {

                        $image_path = public_path($gallery_data['file_path']);
                        if(File::exists($image_path)) {
                            File::delete($image_path);
                        }

                        $image_thumb = public_path($gallery_data['file_thumb']);
                        if(File::exists($image_thumb)) {
                            File::delete($image_thumb);
                        }

                        Db::rollBack();

                        $this->error = 'Can not upload this file!';

                        return false;
                    }

                    if(isset($save_result->id) && $save_result->id) {
                        $gallery_data['id'] = $save_result->id;
                    }
                }
            } else {
                Db::rollBack();

                $this->error = 'File upload can not empty!';

                return false;
            }

            DB::commit();

            return $gallery_data;
        } catch (\Exception $e) {
            $message = 'Lỗi hệ thống';

            if (env('APP_DEBUG') == true) {
                $message = $e->getMessage();
            }

            $this->error = $message;

            return false;
        }
    }

    public function WriteLog($file, $content)
    {
        $f = fopen($file,"a+");
        file_put_contents($file,$content,FILE_APPEND);
        fclose($f);
    }
}
