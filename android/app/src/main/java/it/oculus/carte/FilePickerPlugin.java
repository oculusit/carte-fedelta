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
            Log.d(TAG, "Temp file written: " + tempFile.getAbsolutePath() + " size=" + tempFile.length());

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
        Log.d(TAG, "handleSaveResult: resultCode=" + (result != null ? result.getResultCode() : "null"));

        if (result == null || result.getResultCode() != android.app.Activity.RESULT_OK || result.getData() == null) {
            Log.d(TAG, "User cancelled");
            call.reject("Salvataggio annullato");
            return;
        }

        Intent intent = result.getData();
        Uri uri = intent.getData();
        Log.d(TAG, "Selected URI: " + uri);

        try {
            File tempFile = new File(getContext().getCacheDir(), "export_temp.json");
            if (!tempFile.exists()) {
                Log.e(TAG, "Temp file not found!");
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
            Log.d(TAG, "Written " + totalWritten + " bytes to " + uri);

            tempFile.delete();

            String displayName = "";
            Cursor cursor = getContext().getContentResolver().query(uri, null, null, null, null);
            if (cursor != null) {
                int nameIndex = cursor.getColumnIndex(OpenableColumns.DISPLAY_NAME);
                if (cursor.moveToFirst() && nameIndex >= 0) {
                    displayName = cursor.getString(nameIndex);
                }
                cursor.close();
            }

            JSObject res = new JSObject();
            res.put("uri", uri.toString());
            res.put("filename", displayName);
            call.resolve(res);
        } catch (Exception e) {
            Log.e(TAG, "Write error", e);
            call.reject("Errore scrittura: " + e.getMessage());
        }
    }
}
