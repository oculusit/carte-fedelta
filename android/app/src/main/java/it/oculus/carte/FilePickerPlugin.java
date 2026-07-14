package it.oculus.carte;

import android.content.Intent;
import android.database.Cursor;
import android.net.Uri;
import android.provider.OpenableColumns;
import android.util.Log;

import androidx.activity.result.ActivityResult;
import androidx.core.content.FileProvider;

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

    @PluginMethod
    public void saveFile(PluginCall call) {
        String filename = call.getString("filename", "backup.json");
        String data = call.getString("data", "");
        String mimeType = call.getString("mimeType", "application/json");

        Log.d(TAG, "saveFile: filename=" + filename + " dataLen=" + data.length());

        try {
            File tempFile = new File(getContext().getCacheDir(), "export_temp.json");
            FileOutputStream fos = new FileOutputStream(tempFile);
            fos.write(data.getBytes("UTF-8"));
            fos.flush();
            fos.close();

            Intent intent = new Intent(Intent.ACTION_CREATE_DOCUMENT);
            intent.addCategory(Intent.CATEGORY_OPENABLE);
            intent.setType(mimeType);
            intent.putExtra(Intent.EXTRA_TITLE, filename);

            startActivityForResult(call, intent, "handleSaveResult");
        } catch (Exception e) {
            Log.e(TAG, "saveFile error", e);
            call.reject("Errore preparazione: " + e.getMessage());
        }
    }

    @ActivityCallback
    private void handleSaveResult(PluginCall call, ActivityResult result) {
        if (result == null || result.getResultCode() != android.app.Activity.RESULT_OK || result.getData() == null) {
            call.reject("Salvataggio annullato");
            return;
        }

        Uri uri = result.getData().getData();
        Log.d(TAG, "Saved to URI: " + uri);

        try {
            File tempFile = new File(getContext().getCacheDir(), "export_temp.json");
            if (!tempFile.exists()) {
                call.reject("File temporaneo non trovato");
                return;
            }

            InputStream is = new FileInputStream(tempFile);
            OutputStream os = getContext().getContentResolver().openOutputStream(uri);
            if (os == null) {
                is.close();
                call.reject("Impossibile aprire il file di destinazione");
                return;
            }

            byte[] buffer = new byte[8192];
            int bytesRead;
            long totalWritten = 0;
            while ((bytesRead = is.read(buffer)) != -1) {
                os.write(buffer, 0, bytesRead);
                totalWritten += bytesRead;
            }
            os.flush();
            os.close();
            is.close();
            tempFile.delete();
            Log.d(TAG, "Written " + totalWritten + " bytes");

            String displayName = getDisplayName(uri);

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
            Log.e(TAG, "handleSaveResult error", e);
            call.reject("Errore: " + e.getMessage());
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
