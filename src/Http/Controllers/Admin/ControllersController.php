<?php

namespace Jalmatari\Http\Controllers\Admin;

use Arr;
use Jalmatari\Funs\Funs;
use Jalmatari\Http\Controllers\Core\MyBaseController;
use Jalmatari\Models\controllers;
use Jalmatari\Models\tables;
use Schema;

class ControllersController extends MyBaseController
{

    public function __construct()
    {
        $this->init();
    }

    public function getData($data = [])
    {

        $data = [
            'table_id' => [
                'table_id',
                'formatter' => function ($col, $row) {
                    $table = '';
                    if($col>=1) {
                        $table = tables::find($col);
                        $table = $table ? $table->name : 'Deleted Table!';
                    }
                    return $table;
                }
            ]
        ];

        return parent::getData($data); // TODO: Change the autogenerated stub
    }

    public function reCheck()
    {
        $controllers = Funs::Controllers(true);
        dd($controllers);
        $controllers = array_map(function ($controller) {
            $controllerName = last(explode('\\', $controller));
            $nameSpace = str_replace('\\' . $controllerName, '', $controller);

            return (object) [ 'name' => $controllerName, 'namespace' => $nameSpace ];
        }, $controllers);

        $oldControllers = controllers::all();
        $newControllers = [];
        $msg = "There isn't any new controllers!";
        foreach ($controllers as $controller) {
            $name = $controller->name;
            $space = $controller->namespace;
            if ($oldControllers->where('name', $name)->where('namespace', $space)->count() == 0) {

                $tableName = strtolower(substr($name, 0, -10));
                $table = tables::where('name', $tableName)->first();
                $tableId = $table ? $table->id : 0;
                $tableName = $table ? $tableName : '';
                $title = $table ? $table->title : $tableName;
                $newControllers[] = [
                    "name"       => $name,
                    "title"      => $title,
                    "namespace"  => $space,
                    "url_prefix" => $tableName,
                    "table_id"   => $tableId,
                    "status"     => 1,
                ];
            }


        }
        $countControllers = count($newControllers);
        if ($countControllers) {

            $msg = "We found ($countControllers) controllers: " . implode(',', Arr::pluck($newControllers, 'name'));
            controllers::insert($newControllers);
        }

        return redirect()->back()->with('alert', $msg);
    }

}
