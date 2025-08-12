<?php

namespace Database\Seeders;

use App\Http\Controllers\AiModelController;
use App\Http\Controllers\ProviderIconController;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AiModel;

class AiModelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $aiModel = new AiModelController();
        $providerIcon = new ProviderIconController();
        $providerIcon->populate();
        $aiModel->populateFromApiGetModels();
        //$aiModel->populateNotAuth();\
        //$aiModel->populateForMyApi();
        $aiModel->nullproviderIcons();
    }
}
