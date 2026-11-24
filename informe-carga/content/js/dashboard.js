/*
   Licensed to the Apache Software Foundation (ASF) under one or more
   contributor license agreements.  See the NOTICE file distributed with
   this work for additional information regarding copyright ownership.
   The ASF licenses this file to You under the Apache License, Version 2.0
   (the "License"); you may not use this file except in compliance with
   the License.  You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/
var showControllersOnly = false;
var seriesFilter = "";
var filtersOnlySampleSeries = true;

/*
 * Add header in statistics table to group metrics by category
 * format
 *
 */
function summaryTableHeader(header) {
    var newRow = header.insertRow(-1);
    newRow.className = "tablesorter-no-sort";
    var cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 1;
    cell.innerHTML = "Requests";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 3;
    cell.innerHTML = "Executions";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 7;
    cell.innerHTML = "Response Times (ms)";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 1;
    cell.innerHTML = "Throughput";
    newRow.appendChild(cell);

    cell = document.createElement('th');
    cell.setAttribute("data-sorter", false);
    cell.colSpan = 2;
    cell.innerHTML = "Network (KB/sec)";
    newRow.appendChild(cell);
}

/*
 * Populates the table identified by id parameter with the specified data and
 * format
 *
 */
function createTable(table, info, formatter, defaultSorts, seriesIndex, headerCreator) {
    var tableRef = table[0];

    // Create header and populate it with data.titles array
    var header = tableRef.createTHead();

    // Call callback is available
    if(headerCreator) {
        headerCreator(header);
    }

    var newRow = header.insertRow(-1);
    for (var index = 0; index < info.titles.length; index++) {
        var cell = document.createElement('th');
        cell.innerHTML = info.titles[index];
        newRow.appendChild(cell);
    }

    var tBody;

    // Create overall body if defined
    if(info.overall){
        tBody = document.createElement('tbody');
        tBody.className = "tablesorter-no-sort";
        tableRef.appendChild(tBody);
        var newRow = tBody.insertRow(-1);
        var data = info.overall.data;
        for(var index=0;index < data.length; index++){
            var cell = newRow.insertCell(-1);
            cell.innerHTML = formatter ? formatter(index, data[index]): data[index];
        }
    }

    // Create regular body
    tBody = document.createElement('tbody');
    tableRef.appendChild(tBody);

    var regexp;
    if(seriesFilter) {
        regexp = new RegExp(seriesFilter, 'i');
    }
    // Populate body with data.items array
    for(var index=0; index < info.items.length; index++){
        var item = info.items[index];
        if((!regexp || filtersOnlySampleSeries && !info.supportsControllersDiscrimination || regexp.test(item.data[seriesIndex]))
                &&
                (!showControllersOnly || !info.supportsControllersDiscrimination || item.isController)){
            if(item.data.length > 0) {
                var newRow = tBody.insertRow(-1);
                for(var col=0; col < item.data.length; col++){
                    var cell = newRow.insertCell(-1);
                    cell.innerHTML = formatter ? formatter(col, item.data[col]) : item.data[col];
                }
            }
        }
    }

    // Add support of columns sort
    table.tablesorter({sortList : defaultSorts});
}

$(document).ready(function() {

    // Customize table sorter default options
    $.extend( $.tablesorter.defaults, {
        theme: 'blue',
        cssInfoBlock: "tablesorter-no-sort",
        widthFixed: true,
        widgets: ['zebra']
    });

    var data = {"OkPercent": 100.0, "KoPercent": 0.0};
    var dataset = [
        {
            "label" : "FAIL",
            "data" : data.KoPercent,
            "color" : "#FF6347"
        },
        {
            "label" : "PASS",
            "data" : data.OkPercent,
            "color" : "#9ACD32"
        }];
    $.plot($("#flot-requests-summary"), dataset, {
        series : {
            pie : {
                show : true,
                radius : 1,
                label : {
                    show : true,
                    radius : 3 / 4,
                    formatter : function(label, series) {
                        return '<div style="font-size:8pt;text-align:center;padding:2px;color:white;">'
                            + label
                            + '<br/>'
                            + Math.round10(series.percent, -2)
                            + '%</div>';
                    },
                    background : {
                        opacity : 0.5,
                        color : '#000'
                    }
                }
            }
        },
        legend : {
            show : true
        }
    });

    // Creates APDEX table
    createTable($("#apdexTable"), {"supportsControllersDiscrimination": true, "overall": {"data": [0.9759433962264151, 500, 1500, "Total"], "isController": false}, "titles": ["Apdex", "T (Toleration threshold)", "F (Frustration threshold)", "Label"], "items": [{"data": [0.7441860465116279, 500, 1500, "RP-05 Reporte de morosidad"], "isController": false}, {"data": [0.85, 500, 1500, "00 Inicio de sesión-0"], "isController": false}, {"data": [1.0, 500, 1500, "RP-02 Búsqueda del cliente en el cobro"], "isController": false}, {"data": [1.0, 500, 1500, "00 Inicio de sesión-1"], "isController": false}, {"data": [1.0, 500, 1500, "RP-01 Búsqueda de clientes por CUIL"], "isController": false}, {"data": [1.0, 500, 1500, "RP-01 Búsqueda de clientes por documento"], "isController": false}, {"data": [1.0, 500, 1500, "RP-05 Comprobantes del día"], "isController": false}, {"data": [1.0, 500, 1500, "RP-05 Listado de solicitudes"], "isController": false}, {"data": [0.9978632478632479, 500, 1500, "RP-03 Deuda del cliente"], "isController": false}, {"data": [1.0, 500, 1500, "RP-03 Registro del cobro"], "isController": false}, {"data": [1.0, 500, 1500, "RP-03 Registro del cobro-1"], "isController": false}, {"data": [0.0, 500, 1500, "RP-05 Tablero gerencial"], "isController": false}, {"data": [0.0, 500, 1500, "RP-05 Reporte de cartera"], "isController": false}, {"data": [1.0, 500, 1500, "RP-03 Registro del cobro-0"], "isController": false}, {"data": [1.0, 500, 1500, "00 Apertura de la caja"], "isController": false}, {"data": [1.0, 500, 1500, "00 Inicio de sesión (formulario)"], "isController": false}, {"data": [1.0, 500, 1500, "RP-01 Búsqueda de clientes por apellido"], "isController": false}, {"data": [1.0, 500, 1500, "00 Apertura de la caja-0"], "isController": false}, {"data": [0.85, 500, 1500, "00 Inicio de sesión"], "isController": false}, {"data": [1.0, 500, 1500, "00 Apertura de la caja-1"], "isController": false}]}, function(index, item){
        switch(index){
            case 0:
                item = item.toFixed(3);
                break;
            case 1:
            case 2:
                item = formatDuration(item);
                break;
        }
        return item;
    }, [[0, 0]], 3);

    // Create statistics table
    createTable($("#statisticsTable"), {"supportsControllersDiscrimination": true, "overall": {"data": ["Total", 4240, 0, 0.0, 123.2733490566037, 17, 3522, 57.0, 123.90000000000009, 203.94999999999982, 2998.800000000003, 7.065571503083689, 420.74790256468583, 2.0401069605044553], "isController": false}, "titles": ["Label", "#Samples", "FAIL", "Error %", "Average", "Min", "Max", "Median", "90th pct", "95th pct", "99th pct", "Transactions/s", "Received", "Sent"], "items": [{"data": ["RP-05 Reporte de morosidad", 43, 0, 0.0, 515.5581395348837, 430, 722, 512.0, 599.8, 651.9999999999999, 722.0, 0.07508818496140816, 77.28839906641812, 0.01532561587591241], "isController": false}, {"data": ["00 Inicio de sesión-0", 10, 0, 0.0, 372.5, 230, 636, 302.5, 634.3, 636.0, 636.0, 0.20019619226842306, 0.1575762997737783, 0.07872949865868552], "isController": false}, {"data": ["RP-02 Búsqueda del cliente en el cobro", 468, 0, 0.0, 68.1303418803419, 49, 230, 58.0, 95.0, 126.09999999999991, 197.58000000000004, 0.7876812045463343, 15.680584357837848, 0.16207693955977373], "isController": false}, {"data": ["00 Inicio de sesión-1", 10, 0, 0.0, 40.800000000000004, 26, 78, 37.0, 75.10000000000001, 78.0, 78.0, 0.2021835826930853, 2.645287858875859, 0.0355400828952689], "isController": false}, {"data": ["RP-01 Búsqueda de clientes por CUIL", 313, 0, 0.0, 29.36421725239619, 17, 135, 22.0, 52.80000000000007, 84.30000000000001, 108.86000000000001, 0.5265114495213457, 6.328565380358262, 0.10591929550917693], "isController": false}, {"data": ["RP-01 Búsqueda de clientes por documento", 313, 0, 0.0, 30.635782747603827, 18, 159, 22.0, 58.80000000000007, 87.0, 118.34000000000026, 0.52648665196541, 6.326759372681903, 0.10694260118047391], "isController": false}, {"data": ["RP-05 Comprobantes del día", 465, 0, 0.0, 34.408602150537675, 19, 114, 28.0, 53.400000000000034, 77.79999999999995, 98.0, 0.7847611204026077, 81.22629960766163, 0.16936739024314093], "isController": false}, {"data": ["RP-05 Listado de solicitudes", 311, 0, 0.0, 75.18006430868162, 54, 180, 68.0, 106.0, 123.99999999999989, 164.64, 0.5281570545479247, 73.54655644466239, 0.10779768007862917], "isController": false}, {"data": ["RP-03 Deuda del cliente", 468, 0, 0.0, 100.64529914529909, 44, 978, 78.0, 175.10000000000002, 215.64999999999986, 331.65000000000003, 0.7876613190970843, 19.075110812208415, 0.15417375032398462], "isController": false}, {"data": ["RP-03 Registro del cobro", 467, 0, 0.0, 97.30406852248399, 61, 396, 80.0, 143.2, 189.19999999999993, 317.99999999999966, 0.7876539045370213, 10.222695450855962, 0.539319776205937], "isController": false}, {"data": ["RP-03 Registro del cobro-1", 467, 0, 0.0, 42.28693790149891, 27, 204, 36.0, 63.0, 81.59999999999997, 140.32, 0.7877216626830779, 9.70958766218295, 0.1560163559776604], "isController": false}, {"data": ["RP-05 Tablero gerencial", 44, 0, 0.0, 3188.4772727272725, 2979, 3522, 3183.5, 3340.0, 3498.5, 3522.0, 0.07462572654080925, 1.8830722855103974, 0.013700816982101696], "isController": false}, {"data": ["RP-05 Reporte de cartera", 43, 0, 0.0, 2274.1627906976737, 2066, 2570, 2268.0, 2439.6, 2500.4, 2570.0, 0.07484964794555123, 21.66124197106974, 0.015130739379618266], "isController": false}, {"data": ["RP-03 Registro del cobro-0", 467, 0, 0.0, 54.89079229122058, 34, 251, 44.0, 83.19999999999999, 112.0, 215.91999999999996, 0.7876964180896928, 0.5139707268312677, 0.38333752991812675], "isController": false}, {"data": ["00 Apertura de la caja", 6, 0, 0.0, 45.166666666666664, 37, 62, 42.5, 62.0, 62.0, 62.0, 0.12156576707999027, 1.37521274009239, 0.05923956813761245], "isController": false}, {"data": ["00 Inicio de sesión (formulario)", 10, 0, 0.0, 117.49999999999999, 18, 416, 30.0, 403.6, 416.0, 416.0, 0.19954901921657053, 0.6978369509907609, 0.023969266956677907], "isController": false}, {"data": ["RP-01 Búsqueda de clientes por apellido", 313, 0, 0.0, 78.1884984025559, 54, 198, 65.0, 123.60000000000002, 164.60000000000002, 179.8800000000001, 0.5264432618626336, 105.1082870163828, 0.10507446943601612], "isController": false}, {"data": ["00 Apertura de la caja-0", 6, 0, 0.0, 21.0, 17, 26, 19.5, 26.0, 26.0, 26.0, 0.12161258284857206, 0.07719548715973812, 0.037291358412550414], "isController": false}, {"data": ["00 Inicio de sesión", 10, 0, 0.0, 413.8, 257, 667, 336.5, 666.3, 667.0, 667.0, 0.20008403529482383, 2.7753062536265234, 0.11385641344364633], "isController": false}, {"data": ["00 Apertura de la caja-1", 6, 0, 0.0, 23.5, 19, 41, 19.5, 41.0, 41.0, 41.0, 0.12163230554035151, 1.2987574499787142, 0.021974586450161163], "isController": false}]}, function(index, item){
        switch(index){
            // Errors pct
            case 3:
                item = item.toFixed(2) + '%';
                break;
            // Mean
            case 4:
            // Mean
            case 7:
            // Median
            case 8:
            // Percentile 1
            case 9:
            // Percentile 2
            case 10:
            // Percentile 3
            case 11:
            // Throughput
            case 12:
            // Kbytes/s
            case 13:
            // Sent Kbytes/s
                item = item.toFixed(2);
                break;
        }
        return item;
    }, [[0, 0]], 0, summaryTableHeader);

    // Create error table
    createTable($("#errorsTable"), {"supportsControllersDiscrimination": false, "titles": ["Type of error", "Number of errors", "% in errors", "% in all samples"], "items": []}, function(index, item){
        switch(index){
            case 2:
            case 3:
                item = item.toFixed(2) + '%';
                break;
        }
        return item;
    }, [[1, 1]]);

        // Create top5 errors by sampler
    createTable($("#top5ErrorsBySamplerTable"), {"supportsControllersDiscrimination": false, "overall": {"data": ["Total", 4240, 0, "", "", "", "", "", "", "", "", "", ""], "isController": false}, "titles": ["Sample", "#Samples", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors"], "items": [{"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}]}, function(index, item){
        return item;
    }, [[0, 0]], 0);

});
