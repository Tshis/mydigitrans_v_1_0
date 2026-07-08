//on recupere toutes les tables ayant la classe .table-responsive puis on les parcours 
document.querySelectorAll('.table-responsive').forEach(function(table){

    let labels = []; //on definit un tableau qui contiendra tous les labels

    //on recupere tous les th des table (form table-responsive) pour en recuperer les labels et les push dans labels[]
    table.querySelectorAll('th').forEach(function(th) {
        labels.push(th.innerText);
    });

    //on recupere tous les td et les boucles en recuperant pour cahque td les index
    table.querySelectorAll('td').forEach(function (td, index) {
        /**
         * etant donné que le nombre des td dans notre table depasse le nombre des labels et l'index ira de 0 à labels.lengh
         * il nous faut faire l'operation modulo ainsi nous obtiendront toujours un index compris entre 
         * zero et les nombre des nos labels et le resultat (du modulo) correspondra à chaque fois au bon index.
         * 
         * admettons qu'on 4th(thead) et 5tr (donc 20td car car tr contiendra 4td)
         * l'index du premier tr ira de 0 à 3 et à chaque fois on aura le modulo compris 
         * entre 0 et 3(4 index de 0 à 3) pareil pour les autres tr.
         * 
         * Le 2td du 3e tr correspondra à l'index 10 et si on fait 10%4 = 2 donc on tombe toujours à 2 qui coreespond au bon index du labels
         * 
         * donc labels[index % labels.length] correspont à labels[2] qui est la valeur du label à l'index 2
         */
        td.setAttribute('data-label',labels[index % labels.length])
    })

})