package it.oculus.carte;

import android.content.Intent;
import android.database.Cursor;
import android.net.Uri;
import android.provider.OpenableColumns;
import android.util.Log;

import androidx.activity.result.ActivityResult;

import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import com.getcapacitor.annotation.ActivityCallback;

import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.OutputStream;

@CapacitorPlugin(name = "FilePicker")
public class FilePickerPlugin extends Plugin {

    private static final String TAG = "FilePicker";
    private static String sPendingData = "";
    private static String sPendingMime = "";

    @PluginMethod
    public void saveFile(PluginCall call) {
        String filename = call.getString("filename", "backup.json");
        String data = call.getString("data", "");
        String mimeType = call.getString("mimeType", "application/json");

        Log.d(TAG, "saveFile: filename=" + filename + " dataLen=" + data.length());

        // Store in static field (survives activity restart)
        sPendingData = data;
        sPendingMime = mimeType;

        // Also write to temp file as backup
        try {
            File tempFile = new File(getContext().getCacheDir(), "export_temp.json");
            FileOutputStream fos = new FileOutputStream(tempFile);
            fos.write(data.getBytes("UTF-8"));
            fos.flush();
            fos.close();
            Log.d(TAG, "Temp file written, size=" + tempFile.length());
        } catch (Exception e) {
            Log.e(TAG, "Temp write failed", e);
        }

        Intent intent = new Intent(Intent.ACTION_CREATE_DOCUMENT);
        intent.addCategory(Intent.CATEGORY_OPENABLE);
        intent.setType(mimeType);
        intent.putExtra(Intent.EXTRA_TITLE, filename);

        startActivityForResult(call, intent, "handleSaveResult");
    }

    @ActivityCallback
    private void handleSaveResult(PluginCall call, ActivityResult result) {
        Log.d(TAG, "handleSaveResult: resultCode=" + (result != null ? result.getResultCode() : "null"));

        if (result == null || result.getResultCode() != android.app.Activity.RESULT_OK || result.getData() == null) {
            Log.d(TAG, "Cancelled by user");
            call.reject("Salvataggio annullato");
            return;
        }

        Uri uri = result.getData().getData();
        Log.d(TAG, "Target URI: " + uri);

        // Get data: try static field first, then temp file, then call
        String data = sPendingData;
        if (data == null || data.isEmpty()) {
            Log.d(TAG, "Static field empty, trying call");
            data = call.getString("data", "");
        }
        Log.d(TAG, "Data length from static: " + (sPendingData != null ? sPendingData.length() : "null"));

        if (data == null || data.isEmpty()) {
            // Last resort: read from temp file
            try {
                File tempFile = new File(getContext().getCacheDir(), "export_temp.json");
                if (tempFile.exists()) {
                    byte[] bytes = new byte[(int) tempFile.length()];
                    FileInputStream fis = new FileInputStream(tempFile);
                    fis.read(bytes);
                    fis.close();
                    data = new String(bytes, "UTF-8");
                    Log.d(TAG, "Read from temp file: " + data.length() + " bytes");
                }
            } catch (Exception e) {
                Log.e(TAG, "Temp read failed", e);
            }
        }

        if (data == null || data.isEmpty()) {
            Log.e(TAG, "No data to write!");
            call.reject("Nessun dato da salvare");
            return;
        }

        try {
            OutputStream os = getContext().getContentResolver().openOutputStream(uri, "wt");
            if (os == null) {
                call.reject("Impossibile aprire il file di destinazione");
                return;
            }

            byte[] bytes = data.getBytes("UTF-8");
            os.write(bytes);
            os.flush();
            os.close();
            Log.d(TAG, "Written " + bytes.length + " bytes to " + uri);

            String displayName = getDisplayName(uri);

            // Clear static data
            sPendingData = "";

            // Open share sheet
            Intent shareIntent = new Intent(Intent.ACTION_SEND);
            shareIntent.setType("application/json");
            shareIntent.putExtra(Intent.EXTRA_STREAM, uri);
            shareIntent.putExtra(Intent.EXTRA_SUBJECT, "Backup FidAPPti");
            shareIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            getContext().startActivity(Intent.createChooser(shareIntent, "Condividi backup").addFlags(Intent.FLAG_ACTIVITY_NEW_TASK));

            JSObject res = new JSObject();
            res.put("uri", uri.toString());
            res.put("filename", displayName);
            call.resolve(res);
        } catch (Exception e) {
            Log.e(TAG, "Write error", e);
            call.reject("Errore scrittura: " + e.getMessage());
        }
    }

    private String getDisplayName(Uri uri) {
        Cursor cursor = getContext().getContentResolver().query(uri, null, null, null, null);
        if (cursor != null) {
            int idx = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME);
            if (cursor.moveToFirst() && idx >= 0) {
                String name = cursor.getString(idx);
                cursor.close();
                return name;
            }
            cursor.close();
        }
        return "";
    }
}
