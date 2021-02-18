<?php
/* 
 *
 *  
 */
function splitOrderByWeight(array &$itemInfo, float $weightLimit, array &$orderBins = [], array &$itemsOverWeightLimit = [], string &$errorMsg) : bool
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
		if (empty($orderBins)) {
			$orderBins[$i]['products'][] = $product;
			$orderBins[$i]['weight'] = $product['itemlineWeight'];
			++$i;
			continue 1;
		}

		// Sort the bins so we always try to add to the heaviest orders first
		$keys = array_column($orderBins, 'weight');
		array_multisort($keys, SORT_DESC, $orderBins);

		end($orderBins);
		$lastBinKey = key($orderBins);

		foreach ($orderBins as $key => $orderBin) {
            // If the bin is above weight limit when adding the item and we are out of 
            // existing order bins then we need to create a new one and go to next product
			if (($product['itemlineWeight'] + $orderBin['weight']) > $weightLimit) {
				if ($key === $lastBinKey) {
					$orderBins[$i]['products'][] = $product;
					$orderBins[$i]['weight'] = $product['itemlineWeight'];
					$orderBins[$i]['price'] = $product['itemlineCost'];
                    ++$i;
                    
					continue 2;
                }
                
				continue 1;
			}
            // Otherwise we just add the item to the current weight bin
			$orderBins[$key]['products'][] = $product;
			$orderBins[$key]['weight'] += $product['itemlineWeight'];
            $orderBins[$key]['price'] += $product['itemlineCost'];
            
			continue 2;
		}
	}

	// Now add the separated overweight items to their separate order bins
	if (!empty($itemsOverWeightLimit)) {
		foreach ($itemsOverWeightLimit as $product) {
			$orderBins[$i]['products'][] = $product;
			$orderBins[$i]['weight'] = $product['itemlineWeight'];
			++$i;
		}
	}

	return true;
}
