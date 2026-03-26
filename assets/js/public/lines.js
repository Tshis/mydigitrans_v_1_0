

function swapCities(){
let dep = document.getElementById('departure');
let dest = document.getElementById('destination');

let temp = dep.value;
dep.value = dest.value;
dest.value = temp;
}

function fillRoute(from, to){
document.getElementById('departure').value = from;
document.getElementById('destination').value = to;
}

