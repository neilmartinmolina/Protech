<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- DataTables + Bootstrap 5 skin -->
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            background-color: #0f0f0f;
            color: #e0e0e0;
            min-height: 100vh;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        /* ── Sidebar ── */
        #SidebarMenu {
            width: 260px;
            flex-shrink: 0;
            background-color: #161616;
            border-right: 1px solid #2a2a2a;
            display: flex;
            flex-direction: column;
            padding: 1.5rem 1rem;
            min-height: 100vh;
        }

        #SidebarMenu .brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: #fff;
            text-decoration: none;
            margin-bottom: 1.5rem;
            display: block;
        }

        #SidebarMenu hr { border-color: #2a2a2a; }

        #SidebarMenu .nav-link {
            color: rgba(255,255,255,0.5);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            transition: background 0.15s, color 0.15s;
            font-size: 0.9rem;
        }

        #SidebarMenu .nav-link:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.07);
        }

        #SidebarMenu .nav-link.active {
            color: #fff;
            background-color: rgba(255,255,255,0.12);
        }

        .sidebar-footer { margin-top: auto; }

        .sidebar-footer .dropdown-toggle {
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── Main content ── */
        #main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: auto;
        }

        #main-content h5 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .table {
            --bs-table-bg: #161616;
            --bs-table-striped-bg: #1a1a1a;
            --bs-table-hover-bg: #1e1e1e;
            --bs-table-color: #ccc;
            --bs-table-border-color: #2a2a2a;
        }

        .table-card {
            background-color: #161616;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            padding: 1.5rem;
        }

        /* ── DataTables dark overrides ── */
        table.dataTable thead th,
        table.dataTable thead td {
            background-color: #1e1e1e !important;
            color: #888 !important;
            border-bottom: 1px solid #2a2a2a !important;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        table.dataTable tbody tr {
            background-color: #161616 !important;
        }

        table.dataTable tbody tr:hover > * {
            background-color: #1e1e1e !important;
        }

        table.dataTable tbody td {
            color: #ccc;
            font-size: 0.875rem;
            border-color: #1e1e1e !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button {
            color: #ccc !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #2a2a2a !important;
            border-color: #2a2a2a !important;
            color: #fff !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #2a2a2a !important;
            border-color: #3a3a3a !important;
            color: #fff !important;
        }

        .dataTables_wrapper .dataTables_paginate .page-link {
            --bs-pagination-bg: #161616;
            --bs-pagination-border-color: #2a2a2a;
            --bs-pagination-color: #888;

            --bs-pagination-hover-bg: #1e1e1e;
            --bs-pagination-hover-border-color: #2a2a2a;
            --bs-pagination-hover-color: #fff;

            --bs-pagination-active-bg: #2a2a2a;
            --bs-pagination-active-border-color: #3a3a3a;
            --bs-pagination-active-color: #fff;

            --bs-pagination-disabled-bg: #161616;
            --bs-pagination-disabled-border-color: #2a2a2a;
            --bs-pagination-disabled-color: #444;
        }

        .dataTables_wrapper .dataTables_filter label,
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_info {
            color: #666 !important;
            font-size: 0.825rem;
        }

        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            background-color: #1e1e1e !important;
            border: 1px solid #2a2a2a !important;
            color: #ccc !important;
            border-radius: 5px;
            padding: 3px 8px;
        }

        .dataTables_wrapper .dataTables_filter input:focus,
        .dataTables_wrapper .dataTables_length select:focus {
            outline: none;
            border-color: #444 !important;
            box-shadow: none;
        }
    </style>
</head>

<body>
<div class="layout">

    <!-- ══ SIDEBAR ══ -->
    <div id="SidebarMenu">
        <a href="#" class="brand">⚙ Admin</a>
        <hr>
        <ul class="nav nav-pills flex-column mb-auto">
            <li><a href="#" class="nav-link active">🏠 Home</a></li>
            <li><a href="#" class="nav-link">📊 Dashboard</a></li>
            <li><a href="#" class="nav-link">📦 Orders</a></li>
            <li><a href="#" class="nav-link">🛒 Products</a></li>
            <li><a href="#" class="nav-link">👥 Customers</a></li>
        </ul>
        <hr>
        <div class="sidebar-footer dropdown">
            <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <img src="https://github.com/mdo.png" alt="avatar" width="30" height="30" class="rounded-circle">
                <strong style="color:#ccc;">mdo</strong>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark shadow">
                <li><a class="dropdown-item" href="#">Settings</a></li>
                <li><a class="dropdown-item" href="#">Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#">Sign out</a></li>
            </ul>
        </div>
    </div>

    <!-- ══ MAIN CONTENT ══ -->
    <div id="main-content">
        <h5>Employees</h5>
        <div class="table-card">
            <table id="employeesTable" class="table table-sm w-100">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Office</th>
                        <th>Age</th>
                        <th>Start Date</th>
                        <th>Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Tiger Nixon</td><td>System Architect</td><td>Edinburgh</td><td>61</td><td>2011-04-25</td><td>$320,800</td></tr>
                    <tr><td>Garrett Winters</td><td>Accountant</td><td>Tokyo</td><td>63</td><td>2011-07-25</td><td>$170,750</td></tr>
                    <tr><td>Ashton Cox</td><td>Junior Technical Author</td><td>San Francisco</td><td>66</td><td>2009-01-12</td><td>$86,000</td></tr>
                    <tr><td>Cedric Kelly</td><td>Senior JavaScript Developer</td><td>Edinburgh</td><td>22</td><td>2012-03-29</td><td>$433,060</td></tr>
                    <tr><td>Airi Satou</td><td>Accountant</td><td>Tokyo</td><td>33</td><td>2008-11-28</td><td>$162,700</td></tr>
                    <tr><td>Brielle Williamson</td><td>Integration Specialist</td><td>New York</td><td>61</td><td>2012-12-02</td><td>$372,000</td></tr>
                    <tr><td>Herrod Chandler</td><td>Sales Assistant</td><td>San Francisco</td><td>59</td><td>2012-08-06</td><td>$137,500</td></tr>
                    <tr><td>Rhona Davidson</td><td>Integration Specialist</td><td>Tokyo</td><td>55</td><td>2010-10-14</td><td>$327,900</td></tr>
                    <tr><td>Colleen Hurst</td><td>JavaScript Developer</td><td>San Francisco</td><td>39</td><td>2009-09-15</td><td>$205,500</td></tr>
                    <tr><td>Sonya Frost</td><td>Software Engineer</td><td>Edinburgh</td><td>23</td><td>2008-12-13</td><td>$103,600</td></tr>
                    <tr><td>Jena Gaines</td><td>Office Manager</td><td>London</td><td>30</td><td>2008-12-19</td><td>$90,560</td></tr>
                    <tr><td>Quinn Flynn</td><td>Support Lead</td><td>Edinburgh</td><td>22</td><td>2013-03-03</td><td>$342,000</td></tr>
                    <tr><td>Charde Marshall</td><td>Regional Director</td><td>San Francisco</td><td>36</td><td>2008-10-16</td><td>$470,600</td></tr>
                    <tr><td>Haley Kennedy</td><td>Senior Marketing Designer</td><td>London</td><td>43</td><td>2012-12-18</td><td>$313,500</td></tr>
                    <tr><td>Tatyana Fitzpatrick</td><td>Regional Director</td><td>London</td><td>19</td><td>2010-03-17</td><td>$385,750</td></tr>
                    <tr><td>Michael Silva</td><td>Marketing Designer</td><td>London</td><td>66</td><td>2012-11-27</td><td>$198,500</td></tr>
                    <tr><td>Paul Byrd</td><td>Chief Financial Officer (CFO)</td><td>New York</td><td>64</td><td>2010-06-09</td><td>$725,000</td></tr>
                    <tr><td>Gloria Little</td><td>Systems Administrator</td><td>New York</td><td>59</td><td>2009-04-10</td><td>$237,500</td></tr>
                    <tr><td>Bradley Greer</td><td>Software Engineer</td><td>London</td><td>41</td><td>2012-10-13</td><td>$132,000</td></tr>
                    <tr><td>Dai Rios</td><td>Personnel Lead</td><td>Edinburgh</td><td>35</td><td>2012-09-26</td><td>$217,500</td></tr>
                    <tr><td>Jenette Caldwell</td><td>Development Lead</td><td>New York</td><td>30</td><td>2011-09-03</td><td>$345,000</td></tr>
                    <tr><td>Yuri Berry</td><td>Chief Marketing Officer (CMO)</td><td>New York</td><td>40</td><td>2009-06-25</td><td>$675,000</td></tr>
                    <tr><td>Caesar Vance</td><td>Pre-Sales Support</td><td>New York</td><td>21</td><td>2011-12-12</td><td>$106,450</td></tr>
                    <tr><td>Doris Wilder</td><td>Sales Assistant</td><td>Sydney</td><td>23</td><td>2010-09-20</td><td>$85,600</td></tr>
                    <tr><td>Angelica Ramos</td><td>Chief Executive Officer (CEO)</td><td>London</td><td>47</td><td>2009-10-09</td><td>$1,200,000</td></tr>
                    <tr><td>Gavin Joyce</td><td>Developer</td><td>Edinburgh</td><td>42</td><td>2010-12-22</td><td>$92,575</td></tr>
                    <tr><td>Jennifer Chang</td><td>Regional Director</td><td>Singapore</td><td>28</td><td>2010-11-14</td><td>$357,650</td></tr>
                    <tr><td>Brenden Wagner</td><td>Software Engineer</td><td>San Francisco</td><td>28</td><td>2011-06-07</td><td>$206,850</td></tr>
                    <tr><td>Fiona Green</td><td>Chief Operating Officer (COO)</td><td>San Francisco</td><td>48</td><td>2010-03-11</td><td>$850,000</td></tr>
                    <tr><td>Shou Itou</td><td>Regional Marketing</td><td>Tokyo</td><td>20</td><td>2011-08-14</td><td>$163,000</td></tr>
                    <tr><td>Michelle House</td><td>Integration Specialist</td><td>Sydney</td><td>37</td><td>2011-06-02</td><td>$95,400</td></tr>
                    <tr><td>Suki Burks</td><td>Developer</td><td>London</td><td>53</td><td>2009-10-22</td><td>$114,500</td></tr>
                    <tr><td>Prescott Bartlett</td><td>Technical Author</td><td>London</td><td>27</td><td>2011-05-07</td><td>$145,000</td></tr>
                    <tr><td>Gavin Cortez</td><td>Team Leader</td><td>San Francisco</td><td>22</td><td>2008-10-26</td><td>$235,500</td></tr>
                    <tr><td>Martena Mccray</td><td>Post-Sales Support</td><td>Edinburgh</td><td>46</td><td>2011-03-09</td><td>$324,050</td></tr>
                    <tr><td>Unity Butler</td><td>Marketing Designer</td><td>San Francisco</td><td>47</td><td>2009-12-09</td><td>$85,675</td></tr>
                    <tr><td>Howard Hatfield</td><td>Office Manager</td><td>San Francisco</td><td>51</td><td>2008-12-16</td><td>$164,500</td></tr>
                    <tr><td>Hope Fuentes</td><td>Secretary</td><td>San Francisco</td><td>41</td><td>2010-02-12</td><td>$109,850</td></tr>
                    <tr><td>Vivian Harrell</td><td>Financial Controller</td><td>San Francisco</td><td>62</td><td>2009-02-14</td><td>$452,500</td></tr>
                    <tr><td>Timothy Mooney</td><td>Office Manager</td><td>London</td><td>37</td><td>2008-12-11</td><td>$136,200</td></tr>
                    <tr><td>Jackson Bradshaw</td><td>Director</td><td>New York</td><td>65</td><td>2008-09-26</td><td>$645,750</td></tr>
                    <tr><td>Olivia Liang</td><td>Support Engineer</td><td>Singapore</td><td>64</td><td>2011-02-03</td><td>$234,500</td></tr>
                    <tr><td>Bruno Nash</td><td>Software Engineer</td><td>London</td><td>38</td><td>2011-05-03</td><td>$163,500</td></tr>
                    <tr><td>Sakura Yamamoto</td><td>Support Engineer</td><td>Tokyo</td><td>37</td><td>2009-08-19</td><td>$139,575</td></tr>
                    <tr><td>Thor Walton</td><td>Developer</td><td>New York</td><td>61</td><td>2013-08-11</td><td>$98,540</td></tr>
                    <tr><td>Finn Camacho</td><td>Support Engineer</td><td>San Francisco</td><td>47</td><td>2009-07-07</td><td>$87,500</td></tr>
                    <tr><td>Serge Baldwin</td><td>Data Coordinator</td><td>Singapore</td><td>64</td><td>2012-04-09</td><td>$138,575</td></tr>
                    <tr><td>Zenaida Frank</td><td>Software Engineer</td><td>New York</td><td>63</td><td>2010-01-04</td><td>$125,250</td></tr>
                    <tr><td>Zorita Serrano</td><td>Software Engineer</td><td>San Francisco</td><td>56</td><td>2012-06-01</td><td>$115,000</td></tr>
                    <tr><td>Jennifer Acosta</td><td>Junior JavaScript Developer</td><td>Edinburgh</td><td>43</td><td>2013-02-01</td><td>$75,650</td></tr>
                    <tr><td>Cara Stevens</td><td>Sales Assistant</td><td>New York</td><td>46</td><td>2011-12-06</td><td>$145,600</td></tr>
                    <tr><td>Hermione Butler</td><td>Regional Director</td><td>London</td><td>47</td><td>2011-03-21</td><td>$356,250</td></tr>
                    <tr><td>Lael Greer</td><td>Systems Administrator</td><td>London</td><td>21</td><td>2009-02-27</td><td>$103,500</td></tr>
                    <tr><td>Jonas Alexander</td><td>Developer</td><td>San Francisco</td><td>30</td><td>2010-07-14</td><td>$86,500</td></tr>
                    <tr><td>Shad Decker</td><td>Regional Director</td><td>Edinburgh</td><td>51</td><td>2008-11-13</td><td>$183,000</td></tr>
                    <tr><td>Michael Bruce</td><td>JavaScript Developer</td><td>Singapore</td><td>29</td><td>2011-06-27</td><td>$183,000</td></tr>
                    <tr><td>Donna Snider</td><td>Customer Support</td><td>New York</td><td>27</td><td>2011-01-25</td><td>$112,000</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Scripts — order matters -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#employeesTable').DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            order: [[0, 'asc']]
        });
    });
</script>

</body>
</html>