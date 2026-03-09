<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - ProTech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/e65444583f.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="admin-layout">

    <!-- Sidebar -->
    <aside class="admin-sidebar" id="sidebar">
        <a href="index.php" class="sidebar-brand">
            <div class="brand-icon"><i class="fa-solid fa-microchip"></i></div>
            <div class="brand-text">Pro<span>Tech</span></div>
        </a>

        <div class="sidebar-section-label">Main</div>
        <ul class="sidebar-nav">
            <li><a href="#" class="nav-link active"><i class="fa-solid fa-house"></i> Dashboard</a></li>
            <li><a href="#" class="nav-link"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
        </ul>

        <div class="sidebar-section-label">Management</div>
        <ul class="sidebar-nav">
            <li><a href="#" class="nav-link"><i class="fa-solid fa-box"></i> Products</a></li>
            <li><a href="#" class="nav-link"><i class="fa-solid fa-receipt"></i> Orders</a></li>
            <li><a href="#" class="nav-link"><i class="fa-solid fa-users"></i> Customers</a></li>
            <li><a href="#" class="nav-link"><i class="fa-solid fa-user-tie"></i> Employees</a></li>
        </ul>

        <div class="sidebar-section-label">Settings</div>
        <ul class="sidebar-nav">
            <li><a href="#" class="nav-link"><i class="fa-solid fa-gear"></i> General</a></li>
            <li><a href="#" class="nav-link"><i class="fa-solid fa-bell"></i> Notifications</a></li>
        </ul>

        <div class="sidebar-footer">
            <div class="dropdown">
                <a href="#" class="user-card dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="https://github.com/mdo.png" alt="avatar" class="user-avatar">
                    <div class="user-info">
                        <div class="user-name">Admin User</div>
                        <div class="user-role">Administrator</div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark shadow">
                    <li><a class="dropdown-item" href="#"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
                    <li><a class="dropdown-item" href="#"><i class="fa-solid fa-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="index.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Sign out</a></li>
                </ul>
            </div>
        </div>
    </aside>

    <!-- Main -->
    <div class="admin-main">

        <!-- Top Bar -->
        <div class="admin-topbar">
            <div>
                <h1>Dashboard</h1>
                <span class="breadcrumb-text">Welcome back, Admin</span>
            </div>
            <div class="topbar-actions">
                <button class="topbar-btn d-lg-none" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
                <button class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></button>
                <button class="topbar-btn"><i class="fa-regular fa-bell"></i></button>
            </div>
        </div>

        <!-- Content -->
        <div class="admin-content">

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon orange"><i class="fa-solid fa-dollar-sign"></i></div>
                        <div class="stat-value">$48,250</div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-change up"><i class="fa-solid fa-arrow-up"></i> 12.5%</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-cart-shopping"></i></div>
                        <div class="stat-value">1,284</div>
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-change up"><i class="fa-solid fa-arrow-up"></i> 8.2%</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-value">3,456</div>
                        <div class="stat-label">Customers</div>
                        <div class="stat-change up"><i class="fa-solid fa-arrow-up"></i> 4.1%</div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-icon red"><i class="fa-solid fa-box-open"></i></div>
                        <div class="stat-value">523</div>
                        <div class="stat-label">Products</div>
                        <div class="stat-change down"><i class="fa-solid fa-arrow-down"></i> 2.3%</div>
                    </div>
                </div>
            </div>

            <!-- Employees Table -->
            <div class="table-card">
                <div class="table-card-header">
                    <h5><i class="fa-solid fa-user-tie me-2" style="color: var(--primary);"></i>Employees <span class="badge-count">57</span></h5>
                </div>
                <div class="table-card-body">
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
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function () {
    $('#employeesTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        order: [[0, 'asc']],
        language: {
            search: '<i class="fa-solid fa-magnifying-glass me-1"></i>',
            searchPlaceholder: 'Search employees...'
        }
    });

    document.getElementById('sidebarToggle')?.addEventListener('click', function () {
        document.getElementById('sidebar').classList.toggle('open');
    });
});
</script>

</body>
</html>
