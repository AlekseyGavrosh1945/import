<?php

namespace App\Console\Commands;

use App\Models\Customer;
use Illuminate\Console\Command;
//use Rap2hpoutre\FastExcel\FastExcel;

class AddCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:folder {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import customers';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        if ($this->argument('name') && !empty($this->argument('name')) !== null) {
            $nameFile = $this->argument('name');

            if (!file_exists(public_path($nameFile))) {
                $this->info(" Отсутствует файл импорта");
            } else {
                Customer::truncate();

                $csv = array_map('str_getcsv', file(storage_path('app/' . $nameFile)));

                if (!empty($csv)) {
                    foreach ($csv as $key => $line) {
                        if ($key == 0) {
                            continue;
                        }

                        $errors = [];
                        $fields = explode(",", $line[0]);
                        $id = $fields[0];
                        $name = (string)$fields[1];
                        $email = (string)$fields[2];
                        $age = (int)preg_replace("/[^0-9]/", '', $fields[3]);
                        $location = (string)$fields[4];

                        if (empty(filter_var($email, FILTER_VALIDATE_EMAIL))) {
                            $errors[] = $line[0] . " error = email - " . $email;
                        }

                        if ($age < 18 || $age > 99) {
                            $errors[] = $line[0] . " error = age - " . $age;
                        }

                        if (!empty($errors)) {
                            foreach ($errors as $error) {
                                $this->info($error);
                            }
                        } else {
                            $coordinate = $this->coordinateDelivery($location);

                            if (empty($coordinate)) {
                                $location = 'Unknown';
                            }

                            $customer = new Customer();
                            $customer->id = $id;
                            $customer->name = $name;
                            $customer->email = $email;
                            $customer->age = $age;
                            $customer->location = $location;
                            $customer->save();
                        }
                    }
                }
            }
        }

        return Command::SUCCESS;
    }

    public static function coordinateDelivery($location): array
    {
        $coordinate = [];

        try {
            $apiKey = config('app.keyApiCoordinateYandex');
            $data = json_decode(file_get_contents('https://geocode-maps.yandex.ru/1.x/?apikey=' . $apiKey .
                '&geocode=' . $location . '&format=json'));
            if (isset($data->response->GeoObjectCollection->featureMember)) {
                foreach ($data->response->GeoObjectCollection->featureMember as $item) {
                    if (isset($item->GeoObject->Point->pos)) {
                        $value = explode(' ', $item->GeoObject->Point->pos);
                        $coordinate = [
                            'coordinate_height' => $value[0],
                            'coordinate_latitude' => $value[1],
                        ];
                        break;
                    }
                }
            }
        } catch (\Exception $e) {

        }
        return $coordinate;
    }
}
