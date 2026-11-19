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
    createTable($("#apdexTable"), {"supportsControllersDiscrimination": true, "overall": {"data": [0.9799168975069252, 500, 1500, "Total"], "isController": false}, "titles": ["Apdex", "T (Toleration threshold)", "F (Frustration threshold)", "Label"], "items": [{"data": [1.0, 500, 1500, "RP-05 Reporte de morosidad"], "isController": false}, {"data": [1.0, 500, 1500, "00 Inicio de sesión-0"], "isController": false}, {"data": [1.0, 500, 1500, "RP-02 Búsqueda del cliente en el cobro"], "isController": false}, {"data": [1.0, 500, 1500, "00 Inicio de sesión-1"], "isController": false}, {"data": [1.0, 500, 1500, "RP-01 Búsqueda de clientes por CUIL"], "isController": false}, {"data": [1.0, 500, 1500, "RP-01 Búsqueda de clientes por documento"], "isController": false}, {"data": [1.0, 500, 1500, "RP-05 Listado de solicitudes"], "isController": false}, {"data": [1.0, 500, 1500, "RP-05 Comprobantes del día"], "isController": false}, {"data": [0.9989451476793249, 500, 1500, "RP-03 Deuda del cliente"], "isController": false}, {"data": [1.0, 500, 1500, "RP-03 Registro del cobro"], "isController": false}, {"data": [1.0, 500, 1500, "RP-03 Registro del cobro-1"], "isController": false}, {"data": [0.008771929824561403, 500, 1500, "RP-05 Tablero gerencial"], "isController": false}, {"data": [0.5, 500, 1500, "RP-05 Reporte de cartera"], "isController": false}, {"data": [1.0, 500, 1500, "RP-03 Registro del cobro-0"], "isController": false}, {"data": [1.0, 500, 1500, "00 Apertura de la caja"], "isController": false}, {"data": [0.85, 500, 1500, "00 Inicio de sesión (formulario)"], "isController": false}, {"data": [1.0, 500, 1500, "RP-01 Búsqueda de clientes por apellido"], "isController": false}, {"data": [1.0, 500, 1500, "00 Apertura de la caja-0"], "isController": false}, {"data": [1.0, 500, 1500, "00 Inicio de sesión"], "isController": false}, {"data": [1.0, 500, 1500, "00 Apertura de la caja-1"], "isController": false}]}, function(index, item){
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
    createTable($("#statisticsTable"), {"supportsControllersDiscrimination": true, "overall": {"data": ["Total", 4332, 0, 0.0, 78.29939981532776, 10, 1961, 34.0, 77.0, 151.04999999999836, 1568.3500000000004, 7.227480442257737, 454.8394420907931, 2.0278915109429927], "isController": false}, "titles": ["Label", "#Samples", "FAIL", "Error %", "Average", "Min", "Max", "Median", "90th pct", "95th pct", "99th pct", "Transactions/s", "Received", "Sent"], "items": [{"data": ["RP-05 Reporte de morosidad", 57, 0, 0.0, 266.578947368421, 216, 387, 258.0, 308.8, 364.0, 387.0, 0.09748155546358465, 100.30033531463883, 0.01922975996449619], "isController": false}, {"data": ["00 Inicio de sesión-0", 10, 0, 0.0, 286.70000000000005, 160, 427, 298.0, 425.3, 427.0, 427.0, 0.20210594393581116, 0.15769789961397765, 0.07809894728066452], "isController": false}, {"data": ["RP-02 Búsqueda del cliente en el cobro", 474, 0, 0.0, 43.53164556962028, 30, 136, 38.0, 60.0, 74.25, 128.75, 0.7997179054139552, 15.822527238282193, 0.15935669685714238], "isController": false}, {"data": ["00 Inicio de sesión-1", 10, 0, 0.0, 28.0, 17, 53, 22.0, 52.400000000000006, 53.0, 53.0, 0.20340086241965666, 2.601445385597185, 0.034363622264258396], "isController": false}, {"data": ["RP-01 Búsqueda de clientes por CUIL", 315, 0, 0.0, 16.876190476190477, 10, 80, 13.0, 26.0, 36.19999999999999, 74.0799999999997, 0.5302512549279701, 6.1195196586444425, 0.10304687473697854], "isController": false}, {"data": ["RP-01 Búsqueda de clientes por documento", 315, 0, 0.0, 17.89841269841271, 11, 90, 14.0, 26.0, 39.599999999999966, 79.0, 0.5302325111643401, 6.11772691163549, 0.1040788425234691], "isController": false}, {"data": ["RP-05 Listado de solicitudes", 315, 0, 0.0, 40.84126984126983, 27, 126, 35.0, 59.0, 80.19999999999999, 119.3599999999999, 0.5308589128683571, 73.67403372091651, 0.104720215233797], "isController": false}, {"data": ["RP-05 Comprobantes del día", 473, 0, 0.0, 19.957716701902754, 11, 88, 17.0, 27.600000000000023, 38.299999999999955, 71.77999999999997, 0.8019749268388624, 83.13221372101954, 0.16760022885109038], "isController": false}, {"data": ["RP-03 Deuda del cliente", 474, 0, 0.0, 59.808016877637094, 26, 558, 45.0, 105.5, 122.75, 205.75, 0.7997179054139552, 19.0981403158, 0.15107408315041615], "isController": false}, {"data": ["RP-03 Registro del cobro", 474, 0, 0.0, 58.39873417721517, 38, 257, 50.0, 83.5, 109.25, 185.0, 0.7997664816855162, 10.270692454987826, 0.5366708328201217], "isController": false}, {"data": ["RP-03 Registro del cobro-1", 474, 0, 0.0, 26.177215189873422, 16, 119, 22.0, 36.5, 50.25, 88.25, 0.7998110151187905, 9.749656001640963, 0.1529454669993588], "isController": false}, {"data": ["RP-05 Tablero gerencial", 57, 0, 0.0, 1660.5263157894735, 1489, 1961, 1627.0, 1858.6000000000001, 1910.3999999999999, 1961.0, 0.097235940450663, 2.415438849681766, 0.017187212130439455], "isController": false}, {"data": ["RP-05 Reporte de cartera", 57, 0, 0.0, 1206.9298245614036, 1126, 1397, 1187.0, 1294.4, 1314.0999999999997, 1397.0, 0.09733889132710478, 28.129058455419642, 0.019011502212325154], "isController": false}, {"data": ["RP-03 Registro del cobro-0", 474, 0, 0.0, 32.09493670886078, 20, 188, 27.0, 43.0, 62.5, 130.0, 0.7997921215414053, 0.5215960350600857, 0.38374618400871335], "isController": false}, {"data": ["00 Apertura de la caja", 6, 0, 0.0, 27.166666666666668, 23, 39, 25.0, 39.0, 39.0, 39.0, 0.12223693592747274, 1.3666901166344096, 0.05789542375471121], "isController": false}, {"data": ["00 Inicio de sesión (formulario)", 10, 0, 0.0, 225.4, 10, 782, 20.5, 771.6, 782.0, 782.0, 0.19958088015168146, 0.673585470511925, 0.023973094002594553], "isController": false}, {"data": ["RP-01 Búsqueda de clientes por apellido", 315, 0, 0.0, 44.850793650793655, 32, 156, 39.0, 59.400000000000034, 81.0, 128.67999999999995, 0.5302057366577514, 105.63133919343925, 0.10243970645538107], "isController": false}, {"data": ["00 Apertura de la caja-0", 6, 0, 0.0, 13.666666666666666, 11, 22, 12.0, 22.0, 22.0, 22.0, 0.12226682697206204, 0.07761077883968781, 0.036656167851975625], "isController": false}, {"data": ["00 Inicio de sesión", 10, 0, 0.0, 315.5, 178, 468, 321.0, 464.3, 468.0, 468.0, 0.20203652820429935, 2.7416396337582833, 0.11220524764627444], "isController": false}, {"data": ["00 Apertura de la caja-1", 6, 0, 0.0, 13.333333333333332, 11, 17, 13.0, 17.0, 17.0, 17.0, 0.12226931855233127, 1.28943981547522, 0.021253846388979456], "isController": false}]}, function(index, item){
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
    createTable($("#top5ErrorsBySamplerTable"), {"supportsControllersDiscrimination": false, "overall": {"data": ["Total", 4332, 0, "", "", "", "", "", "", "", "", "", ""], "isController": false}, "titles": ["Sample", "#Samples", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors", "Error", "#Errors"], "items": [{"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}, {"data": [], "isController": false}]}, function(index, item){
        return item;
    }, [[0, 0]], 0);

});
