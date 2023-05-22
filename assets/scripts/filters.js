import object_hash from 'object-hash';

const hash = function (value){

	return object_hash(value)
};

const formatNumber = function (number){

	if( number === Math.round(number) )
		return number

	return number.toFixed(2);
};

export {hash, formatNumber}