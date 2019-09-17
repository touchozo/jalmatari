<?php

/*
 * Jamal Al-Matari 2019.
 * jalmatari@gmail.com
 */

namespace Jalmatari\Http\Controllers\Admin;

use Jalmatari\Funs\Funs;
use Jalmatari\Funs\TablesSourceFuns;
use Jalmatari\Http\Controllers\Core\MyBaseController;
use Jalmatari\Http\Controllers\Traits\TablesControllerTrait;
use Jalmatari\Models\controllers;
use Jalmatari\Models\menu;
use Jalmatari\Models\myModel;
use Jalmatari\Models\tables;
use Jalmatari\Models\tables_cols;
use Redirect;

class TablesController extends MyBaseController
{
    use TablesControllerTrait;

    public function __construct()
    {
        $this->init();
    }

    public function add()
    {

        dd(request()->all());

        return parent::add(); // TODO: Change the autogenerated stub
    }

    public function edit($id)
    {
        $table = tables::find($id);
        $cols = tables_cols::where('TABLE_ID', $id)->get();
        $sourceFuns = get_class_methods(TablesSourceFuns::class);
        $modelClass = '\Jalmatari\Models\\' . $table->name;//If model In Jalmatari Vendor
        if (!class_exists($modelClass)) {
            $modelClass = '\App\Jalmatari\Models\\' . $table->name;//If model generated by Jalmatari
            if (!class_exists($modelClass))
                $modelClass = '\App\Models\\' . $table->name;//If model in defualt namespace
        }
        if (class_exists($modelClass)) {
            $modelFuns = array_diff(get_class_methods($modelClass), get_class_methods(myModel::class));
            $sourceFuns = array_merge($modelFuns, $sourceFuns);
        }
        $sourceFuns = Funs::ArrayByValuesAsKeys($sourceFuns);
        $this->newEditData = [
            'table'      => $table,
            'cols'       => $cols,
            'sourceFuns' => $sourceFuns,
        ];

        return parent::edit($id); // TODO: Change the autogenerated stub
    }

    public function update()
    {
        $data = request()->all();
        $cols = Funs::IsIn($data, 'cols', []);
        unset($data["cols"]);
        //dd($cols);
        $this->saveCols(null, $cols);
        $this->saveUpdateData = $data;

        parent::update();
        return redirect()->back();
    }

    public function recheck()
    {
        $tables = $this->tablesOfDataBase();

        return redirect()->back();
    }


    public function getData($data = [])
    {
        $this->listBtns = [
            'edit',
            'delete',
            //[ 'default ', 'cols', 'تعديل خصائص حقول الجدول', 'wrench' ],
            //[ 'primary ', 'route', 'تعديل الروابط المرتبطة بالجدول', 'sitemap' ],
            [ ' bg-teal ', 'genMolel', 'توليد موديل (Model) تلقائي لهذا الجدول', 'table' ],
            [ ' bg-gray ', 'genController', 'توليد كونترولر (Controller) تلقائي لهذا الجدول', 'cube' ]
        ];

        return parent::getData(); // TODO: Change the autogenerated stub
    }

    public function genController($tableId)
    {


        $table = tables::find($tableId);
        $tableName=$table->name;
        $tableController = ucfirst($tableName) . 'Controller';
        $namespace = 'App\Http\Controllers';
        $isControllerExist = class_exists($namespace . '\\' . $tableController);


        $msg = "تم إنشاء كونترولر (Controller) لجدول {$tableName} بنجاح";
        if ($isControllerExist)
            $msg = "يوجد كونترولر (Controller) مسبقاً لجدول {$tableName}.";
        else {
            Funs::Artisan('make:controller', [ 'name' => $tableController ]);
            $this->changeCotrollerAfterCreated($tableController);
            controllers::insert([
                "name"       => $tableController,
                "title"      => $table->title,
                "namespace"  => $namespace,
                "url_prefix" => $tableName,
                "table_id"   => $tableId,
                "status"     => 1,
            ]);
        }

        return redirect()->back()->with('alert', $msg);

    }

    public function genMolel($tableId)
    {
        $table = tables::find($tableId);
        $tableName=$table->name;
        $namespace='App\Jalmatari\Models\\';
        $isModelExist = class_exists($namespace . $tableName);


        $msg = "تم إنشاء موديل (Model) لجدول {$tableName} بنجاح";

        if (!$isModelExist && file_exists(app_path("Jalmatari/Models/{$tableName}.php"))) {

            $msg = 'يوجد موديل (Model) مسبقاً لهذا الجدول\n لكن لم يتم تحميله حتى الآن، يحتاج إلى تنفيذ الأمر:\ncomposer dump-autoload\n أو الأمر:\nphp artisan optimize';
        }
        else if ($isModelExist)
            $msg = 'يوجد موديل (Model) مسبقاً لهذا الجدول.';
        else {
            $table->namespace=$namespace;
            $table->save();
            Funs::Artisan('make:model', [ 'name' => 'Jalmatari\\Models\\' . $tableName ]);
            $this->changeModelAfterCreated($tableName);
        }

        return redirect()->back()->with('alert', $msg);

    }

    public function cols($tableId)
    {

        $table = tables::find($tableId);
        $cols = tables_cols::where('TABLE_NAME', $table->nameWithPrefix())->get();
        $sourceFuns = get_class_methods('\Jalmatari\TablesSourceFuns');
        $sourceFuns = Funs::ArrayByValuesAsKeys($sourceFuns);
        $data = [
            'table'      => $table,
            'cols'       => $cols,
            'sourceFuns' => $sourceFuns,
        ];

        return view('admin.tables.cols', $data);
    }

    public function saveCols($table, $data = null)
    {
        if (is_null($data))
            $data = request()->all();
        $cols = $data['COLUMN_NAME'];
        foreach ($cols as $key => $col) {
            $source = $data['SOURCE'][ $key ];
            if ($source == 'function') {
                $source = [ 'function' => Funs::IsIn($data, 'source_fun_name_' . $col, '') ];
                $source = json_encode($source, JSON_UNESCAPED_UNICODE);
            }
            else
                $source = '';
            $attr = '';
            $attrsKeys = Funs::IsIn($data, 'attr_key_' . $col, '');
            $attrsVals = Funs::IsIn($data, 'attr_val_' . $col, '');
            if ($attrsVals != '') {
                $attr = [];
                foreach ($attrsKeys as $attrKey => $attrVal) {
                    $attr[ $attrVal ] = $attrsVals[ $attrKey ];
                }
                $attr = json_encode($attr, JSON_UNESCAPED_UNICODE);
            }
            $title = $data['TITLE'][ $key ];
            $type = $data['TYPE'][ $key ];
            $COLUMN_COMMENT = [
                'title' => $title,
                'type'  => $type
            ];
            $para = [];
            if ($attr != '')
                $para = json_decode($attr);
            if (is_object($para))
                $para = (array) $para;
            if ($source != '')
                $para['source'] = json_decode($source);
            if (count($para) >= 1)
                $COLUMN_COMMENT['para'] = $para;
            $COLUMN_COMMENT = json_encode($COLUMN_COMMENT, JSON_UNESCAPED_UNICODE);

            $id = $data['ID'][ $key ];
            tables_cols::where('ID', $id)->update([
                'COLUMN_NAME'    => $col,
                'TITLE'          => $title,
                'TYPE'           => $type,
                'SOURCE'         => $source,
                'ATTR'           => $attr,
                'COLUMN_COMMENT' => $COLUMN_COMMENT,
                'SHOW_IN_LIST'   => isset($data[ 'SHOW_IN_LIST_' . $col ]) ? 1 : 0
            ]);


        }

        return redirect()->route($this->mainRoute);
    }

    function addTablesToMenus()
    {
        $menus = menu::all()->pluck('name');
        $tables = tables::where('status', 1)->whereNotIn('name', $menus)->get();
        $data = [];

        foreach ($tables as $table) {
            $data[] = [
                'title' => $table->title,
                'name'  => $table->name,
                'link'  => 'admin.' . $table->name,
                //'status' => 1,
            ];
        }
        menu::insert($data);

        return redirect()->route($this->mainRoute)->with('alert', 'تم توليد القوائم بنجاح لعدد (' . count($data) . ') قوائم/قائمة.');

    }

}
