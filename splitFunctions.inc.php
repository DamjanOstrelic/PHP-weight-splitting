<?php
/**
* Given a list of items with varying weights and a specified weight limit, 
* returns the optimal filling of n amount of weight bins
*
* @param array &$itemInfo array containing weights and quantities of each item line
* @param float $weightLimit  weight limit (dimensionless)
* @param array &$weightBins can be specified for pre-existing weight bins
* @param array &$itemsOverWeightLimit can be specified for pre-existing overweight items
* @return bool true on success, false on failure
*/
function splitOrderByWeight(array &$itemInfo, float $weightLimit, array &$weightBins = [], array &$itemsOverWeightLimit = []) : bool
{
	// Get all the combined weights for individual item lines
	foreach ($itemInfo as $key => $product) {
		if (!empty($product)) {
			$itemlineWeight = $product['weight'] * $product['quantity'];
			$itemInfo[$key]['itemlineWeight'] = $itemlineWeight;

			// Set aside all itemlines that are above the weight limit on their own
			// as they must go in their separate weight bins
			if ($itemlineWeight > $weightLimit) {
				$itemsOverWeightLimit[] = $itemInfo[$key];
				unset($itemInfo[$key]);
			}
		} else {
			errorlog(
				__FUNCTION__,
				"Failed to get product details for product id: " . $product['product_id'],
				print_r($product, true)
			);

			return false;
		}
	}

	// Create bins as close to weight limit as possible
	// First sort products by weight so we start with the heaviest ones
	$keys = array_column($itemInfo, 'itemlineWeight');
	array_multisort($keys, SORT_DESC, $itemInfo);

	$i = 0;
	foreach ($itemInfo as $product) {
		if (empty($weightBins)) {
			$weightBins[$i]['products'][] = $product;
			$weightBins[$i]['weight'] = $product['itemlineWeight'];
			++$i;
			continue 1;
		}

		// Sort the bins so we always try to add to the heaviest orders first
		$keys = array_column($weightBins, 'weight');
		array_multisort($keys, SORT_DESC, $weightBins);

		end($weightBins);
		$lastBinKey = key($weightBins);

		foreach ($weightBins as $key => $orderBin) {
			// If the bin is above weight limit when adding the item and we are out of 
			// existing order bins then we need to create a new one and go to next product
			if (($product['itemlineWeight'] + $orderBin['weight']) > $weightLimit) {
				if ($key === $lastBinKey) {
					$weightBins[$i]['products'][] = $product;
					$weightBins[$i]['weight'] = $product['itemlineWeight'];
					$weightBins[$i]['price'] = $product['itemlineCost'];
					++$i;
					
					continue 2;
				}
				
				continue 1;
			}
			// Otherwise we just add the item to the current weight bin
			$weightBins[$key]['products'][] = $product;
			$weightBins[$key]['weight'] += $product['itemlineWeight'];
			$weightBins[$key]['price'] += $product['itemlineCost'];
			
			continue 2;
		}
	}

	// Now add the separated overweight items to their separate order bins
	if (!empty($itemsOverWeightLimit)) {
		foreach ($itemsOverWeightLimit as $product) {
			$weightBins[$i]['products'][] = $product;
			$weightBins[$i]['weight'] = $product['itemlineWeight'];
			++$i;
		}
	}

	return true;
}
