<?php

require '../vendor/autoload.php';

use Cozy\ValueObjects\Matrix;


// EXTRACT PHASE

$input_data = [];

if (($handle = fopen('../resources/input.csv', 'rb')) !== false) {
    $i = -1;

    while (($row = fgetcsv($handle, 100, ',')) !== false) {
        $i++;

        // Ignoring the headers
        if ($i === 0) {
            continue;
        }

        $input_data[$i] = [
            'period' => $i,
            'date' => $row[0],
            'sales' => $row[1],
            'mbudget' => $row[2],
            'frelease' => $row[3],
            'discount' => $row[4],
        ];
    }

    fclose($handle);
}


// TRANSFORM PHASE

$dependent_var = [];
$independent_vars = [];
$future_independent_vars = [];
$result = [];

foreach ($input_data as $datum) {
    $dt = new DateTimeImmutable($datum['date']);

    $vars = [
        1, // β₀
        $datum['period'],
        (float)$datum['mbudget'],
        (float)$datum['frelease'],
        (float)$datum['discount'],
    ];

    if ($dt->format('m') === '01') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '02') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '03') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '04') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '05') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '06') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '07') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '08') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '09') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '10') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    if ($dt->format('m') === '11') {
        $vars[] = 1;
    } else {
        $vars[] = 0;
    }

    // Prepare the observable variables with existent data sales revenue
    if ($datum['sales']) {
        $dependent_var[] = [(float)$datum['sales']];
        $independent_vars[] = $vars;
    }

    $result[$datum['period']] = [
        'date' => $datum['date'],
        'month' => $dt->format('M Y'),
        'sales' => $datum['sales'] ? (float)$datum['sales'] : null,
        'forecast' => null,
        'error_rate' => null,
        'independent_vars' => $vars,
    ];
}


// SUPERVISED TRAINING PHASE

/**
 * Following the Wikipedia article Linear Least Squares (https://en.wikipedia.org/wiki/Linear_least_squares#Main_formulations).
 * We get the coefficients using the equation β = (Xᵀ · X)⁻¹ · Xᵀ · y , where y is a vector whose 𝓲th element is
 * the 𝓲th observation of the dependent variable, and X is a matrix whose 𝓲𝓳 element is the 𝓲th observation of
 * the 𝓲th independent variable.
 */

$X = new Matrix($independent_vars);
$y = new Matrix($dependent_var);

$B = $X
    ->transpose()
    ->multiply($X)
    ->inverse()
    ->multiply($X->transpose()->multiply($y));

// Get the coefficient vector of the least-squares hyperplane
$coefficients = $B->getColumnValues(1);


// PREDICTION PHASE

/**
 * Following the Wikipedia article Linear regression (https://en.wikipedia.org/wiki/Linear_regression#Introduction)
 * We pick the model for multiple linear regression to predict the dependent variable using this equation
 * Yₖ = β₀ + β₁Xₖ₁ + β₂Xₖ₂ + ··· + βₚXₖₚ + εₖ
 * for each observation k = 1, ..., n.
 * In the formula above we consider n observations of one dependent variable and p independent variables.
 * Thus, Yₖ is the kᵗʰ observation of the dependent variable, Xₖₕ is kᵗʰ observation of the hᵗʰ independent
 * variable, h = 1, 2, ..., p. The values βₕ represent parameters to be estimated, and εₖ is the ith independent
 * identically distributed normal error.
 */

$error_rates = [];

foreach ($result as $period => $data) {
    $forecast = 0;
    foreach ($coefficients as $index => $coefficient) {
        $forecast += round($coefficient * $data['independent_vars'][$index], 3);
    }

    $result[$period]['forecast'] = $forecast;

    if ($data['sales']) {
        $error_rate = round(abs($data['sales'] - $forecast) / $data['sales'], 3);
        $error_rates[] = $result[$period]['error_rate'] = $error_rate;
    }

    unset($result[$period]['independent_vars']);
}

$average_error_rate = round(array_sum($error_rates) / count($error_rates) * 100, 1);


// LOAD PHASE

$fp = fopen('../resources/result.csv', 'wb');

foreach ($result as $data) {
    fputcsv($fp, $data);
}

echo "\nAverage Error Rate: {$average_error_rate}%\n";
