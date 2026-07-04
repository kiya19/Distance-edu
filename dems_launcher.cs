using System;
using System.IO;
using System.Diagnostics;
using System.Windows.Forms;
using System.Drawing;
using System.Threading;

public class DemsLauncher : ApplicationContext {
    private NotifyIcon trayIcon;
    private Process phpProcess;
    private int port = 8080;
    private string phpPath = "";
    private string extDir = "";

    [STAThread]
    public static void Main() {
        Application.EnableVisualStyles();
        Application.SetCompatibleTextRenderingDefault(false);
        Application.Run(new DemsLauncher());
    }

    public DemsLauncher() {
        // 1. Find PHP
        if (!FindPhp()) {
            MessageBox.Show(
                "Could not find php.exe on this system!\n\n" +
                "Please make sure PHP is installed or place a copy of PHP in a 'php' folder next to this executable.",
                "DEMS Launcher Error", MessageBoxButtons.OK, MessageBoxIcon.Error
            );
            Exit();
            return;
        }

        // 2. Start PHP Server
        if (!StartServer()) {
            Exit();
            return;
        }

        // 3. Create System Tray Icon
        InitializeTray();

        // 4. Open in browser
        OpenBrowser();
    }

    private bool FindPhp() {
        // Check local folder
        string localPhp = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "php", "php.exe");
        if (File.Exists(localPhp)) {
            phpPath = localPhp;
            extDir = Path.Combine(Path.GetDirectoryName(phpPath), "ext");
            return true;
        }

        // Check the user's specific Winget PHP package path
        string wingetPhp = @"C:\Users\Eyoba\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe";
        if (File.Exists(wingetPhp)) {
            phpPath = wingetPhp;
            extDir = Path.Combine(Path.GetDirectoryName(phpPath), "ext");
            return true;
        }

        // Check system PATH
        try {
            ProcessStartInfo psi = new ProcessStartInfo("where", "php") {
                RedirectStandardOutput = true,
                UseShellExecute = false,
                CreateNoWindow = true
            };
            using (Process p = Process.Start(psi)) {
                string output = p.StandardOutput.ReadToEnd().Trim();
                p.WaitForExit();
                if (p.ExitCode == 0 && !string.IsNullOrEmpty(output)) {
                    string[] lines = output.Split(new[] { Environment.NewLine }, StringSplitOptions.RemoveEmptyEntries);
                    if (lines.Length > 0 && File.Exists(lines[0])) {
                        phpPath = lines[0];
                        extDir = Path.Combine(Path.GetDirectoryName(phpPath), "ext");
                        return true;
                    }
                }
            }
        } catch {}

        // Check typical WAMP directories
        string wampDir = @"C:\wamp64\bin\php";
        if (Directory.Exists(wampDir)) {
            string[] subdirs = Directory.GetDirectories(wampDir, "php*");
            if (subdirs.Length > 0) {
                Array.Sort(subdirs); // Get latest php version
                string wampPhp = Path.Combine(subdirs[subdirs.Length - 1], "php.exe");
                if (File.Exists(wampPhp)) {
                    phpPath = wampPhp;
                    extDir = Path.Combine(Path.GetDirectoryName(phpPath), "ext");
                    return true;
                }
            }
        }

        return false;
    }

    private bool StartServer() {
        try {
            string baseDir = AppDomain.CurrentDomain.BaseDirectory;
            string publicDir = Path.Combine(baseDir, "public");

            if (!Directory.Exists(publicDir)) {
                MessageBox.Show(
                    "Could not find the 'public' directory!\n" +
                    "Make sure this launcher is run from the project root folder.",
                    "DEMS Launcher Error", MessageBoxButtons.OK, MessageBoxIcon.Error
                );
                return false;
            }

            // Kill any existing PHP processes on our port
            StopServer();

            // Construct command arguments to load required extensions on the fly
            string extArgs = "";
            if (Directory.Exists(extDir)) {
                extArgs = string.Format("-d extension_dir=\"{0}\" ", extDir) +
                          "-d extension=php_pdo_mysql.dll " +
                          "-d extension=php_pdo_sqlite.dll " +
                          "-d extension=php_mbstring.dll " +
                          "-d extension=php_openssl.dll " +
                          "-d extension=php_curl.dll " +
                          "-d extension=php_fileinfo.dll";
            }

            ProcessStartInfo psi = new ProcessStartInfo {
                FileName = phpPath,
                Arguments = string.Format("{0} -S localhost:{1} -t \"{2}\"", extArgs, port, publicDir),
                WorkingDirectory = baseDir,
                UseShellExecute = false,
                CreateNoWindow = true,
                RedirectStandardError = true,
                RedirectStandardOutput = true
            };

            phpProcess = new Process {
                StartInfo = psi,
                EnableRaisingEvents = true
            };

            phpProcess.Exited += (sender, e) => {
                // If exited prematurely, notify user
                Notify("Server stopped unexpectedly.");
            };

            phpProcess.Start();
            return true;
        } catch (Exception ex) {
            MessageBox.Show(
                "Failed to start PHP development server:\n" + ex.Message,
                "DEMS Launcher Error", MessageBoxButtons.OK, MessageBoxIcon.Error
            );
            return false;
        }
    }

    private void StopServer() {
        if (phpProcess != null && !phpProcess.HasExited) {
            try {
                phpProcess.Kill();
                phpProcess.Dispose();
            } catch {}
        }

        // Additional cleanup: kill any orphan PHP processes started by this port
        try {
            foreach (var proc in Process.GetProcessesByName("php")) {
                // Check if it belongs to our winget or wamp installation to avoid killing system php
                if (proc.MainModule.FileName.Equals(phpPath, StringComparison.OrdinalIgnoreCase)) {
                    proc.Kill();
                }
            }
        } catch {}
    }

    private void InitializeTray() {
        trayIcon = new NotifyIcon();
        
        // Use system default icon or draw a nice fallback
        trayIcon.Icon = SystemIcons.Application;
        
        trayIcon.Text = "DEMS Web Server (Port " + port + ")";
        trayIcon.Visible = true;

        ContextMenu contextMenu = new ContextMenu();
        contextMenu.MenuItems.Add("Open DEMS Web App", (s, e) => OpenBrowser());
        contextMenu.MenuItems.Add("Restart Web Server", (s, e) => {
            if (StartServer()) {
                Notify("Server restarted successfully!");
                OpenBrowser();
            }
        });
        contextMenu.MenuItems.Add("-");
        contextMenu.MenuItems.Add("Database Setup Guide", (s, e) => ShowDatabaseHelp());
        contextMenu.MenuItems.Add("Exit App", (s, e) => Exit());

        trayIcon.ContextMenu = contextMenu;
        trayIcon.DoubleClick += (s, e) => OpenBrowser();

        trayIcon.ShowBalloonTip(3000, "DEMS Server Started", "DEMS is running on http://localhost:" + port + "/", ToolTipIcon.Info);
    }

    private void OpenBrowser() {
        string url = "http://localhost:" + port + "/";
        try {
            Process.Start(url);
        } catch {
            // Fallback for Windows
            Process.Start(new ProcessStartInfo(url) { UseShellExecute = true });
        }
    }

    private void ShowDatabaseHelp() {
        MessageBox.Show(
            "This application uses MySQL by default.\n\n" +
            "To connect to the database:\n" +
            "1. Make sure MySQL/MariaDB or WAMP Server is running.\n" +
            "2. Import the database schema from the 'database/dems.sql' file into phpMyAdmin.\n" +
            "3. Verify connection settings in 'config/database.php'.",
            "DEMS Database Help", MessageBoxButtons.OK, MessageBoxIcon.Information
        );
    }

    private void Notify(string message) {
        if (trayIcon != null) {
            trayIcon.ShowBalloonTip(3000, "DEMS Server Notice", message, ToolTipIcon.Warning);
        }
    }

    private void Exit() {
        StopServer();
        if (trayIcon != null) {
            trayIcon.Visible = false;
            trayIcon.Dispose();
        }
        Application.Exit();
    }
}
